<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use InternalsCS\Support\FileSystem;
use InternalsCS\Support\UnifiedDiff;

use function array_filter;
use function array_unique;
use function array_values;
use function basename;
use function glob;
use function is_dir;
use function realpath;
use function sort;

final readonly class FixtureValidator
{
    public function __construct(
        private UnifiedDiff $diff = new UnifiedDiff(),
        private FileSystem $files = new FileSystem(),
    ) {}

    public function validate(FixtureValidationOptions $options): FixtureValidationResult
    {
        $result = new FixtureValidationResult();

        foreach ($this->fixtureDirs($options) as $fixtureDir) {
            $result->fixtures++;
            $case = basename($fixtureDir);
            $files = new FixturePairFiles($fixtureDir);
            $oldPath = $files->oldPath();
            $newPath = $files->newPath();
            $diffPath = $files->diffPath();
            $hasNew = $files->hasNew();
            $hasDiff = $files->hasDiff();

            if (!$files->hasOld()) {
                $this->fail($result, $case . ': missing old.phpt', $options->failFast);
                continue;
            }

            if ($hasNew && !$hasDiff) {
                $this->fail($result, $case . ': new.phpt exists but ran.diff is missing', $options->failFast);
                continue;
            }

            if ($hasDiff && !$hasNew) {
                $this->fail($result, $case . ': ran.diff exists but new.phpt is missing', $options->failFast);
                continue;
            }

            $realOldPath = realpath($oldPath);
            $rewrite = $options->runner->printFile(false === $realOldPath ? $oldPath : $realOldPath);

            if ($hasNew) {
                $this->validateHandled($result, $case, $oldPath, $newPath, $diffPath, $rewrite, $options);
                continue;
            }

            $result->oldOnly++;
            $this->validateOldOnly($result, $case, $oldPath, $newPath, $diffPath, $rewrite, $options);
        }

        return $result;
    }

    /** @return list<string> */
    private function fixtureDirs(FixtureValidationOptions $options): array
    {
        if ([] === $options->cases) {
            $fixtureDirs = glob($options->fixturesDir . '/*', \GLOB_ONLYDIR);

            if (false === $fixtureDirs) {
                return [];
            }

            $dirs = array_filter(
                $fixtureDirs,
                static fn(string $dir): bool => new FixturePairFiles($dir)->containsFixtureFiles(),
            );

            sort($dirs);

            return $dirs;
        }

        $dirs = [];

        foreach ($options->cases as $case) {
            $dir = is_dir($case) ? $case : $options->fixturesDir . DIRECTORY_SEPARATOR . $case;

            if (!is_dir($dir)) {
                throw new \InvalidArgumentException('Fixture case does not exist: ' . $case);
            }

            $realDir = realpath($dir);
            $dirs[] = false === $realDir ? $dir : $realDir;
        }

        sort($dirs);

        return array_values(array_unique($dirs));
    }

    /** @param array{changed: bool, failed: bool, output: string, failure: string|null} $rewrite */
    private function validateHandled(
        FixtureValidationResult $result,
        string $case,
        string $oldPath,
        string $newPath,
        string $diffPath,
        array $rewrite,
        FixtureValidationOptions $options,
    ): void {
        if ($rewrite['failed']) {
            $this->fail($result, $case . ': rewrite failed verification: ' . ($rewrite['failure'] ?? 'unknown reason'), $options->failFast);
            return;
        }

        if (!$rewrite['changed']) {

            if ($options->update && $options->refreshPairs) {
                $this->deletePair($newPath, $diffPath);
                $result->deletedPairs++;
                $result->oldOnly++;
                return;
            }

            $this->fail($result, $case . ': expected handled rewrite, but fixer reported no change', $options->failFast);
            return;
        }

        $expected = $this->files->read($newPath, 'fixture');

        if ($rewrite['output'] !== $expected) {
            if (!$options->update) {
                $this->fail($result, $case . ': rewritten old.phpt does not match new.phpt', $options->failFast);
                return;
            }

            $this->writePair($oldPath, $newPath, $diffPath, $rewrite['output']);
            $result->updated++;
            $result->handled++;
            return;
        }

        $diff = $this->diff->betweenFiles($oldPath, $newPath, 'old.phpt', 'new.phpt');
        $expectedDiff = $this->files->read($diffPath, 'fixture diff');

        if ($diff !== $expectedDiff) {
            if (!$options->update) {
                $this->fail($result, $case . ': ran.diff does not match old.phpt/new.phpt', $options->failFast);
                return;
            }

            $this->writeDiff($diffPath, $diff);
            $result->updated++;
        }

        $result->handled++;
    }

    /** @param array{changed: bool, failed: bool, output: string, failure: string|null} $rewrite */
    private function validateOldOnly(
        FixtureValidationResult $result,
        string $case,
        string $oldPath,
        string $newPath,
        string $diffPath,
        array $rewrite,
        FixtureValidationOptions $options,
    ): void {
        if ($rewrite['failed'] || !$rewrite['changed']) {
            return;
        }

        if (!$options->update) {
            $this->fail($result, $case . ': fixer now produces a verified rewrite; new.phpt is missing', $options->failFast);
            return;
        }

        $this->writePair($oldPath, $newPath, $diffPath, $rewrite['output']);
        $result->updated++;
    }

    private function writePair(string $oldPath, string $newPath, string $diffPath, string $newContents): void
    {
        $this->files->write($newPath, $newContents, 'fixture');

        $this->writeDiff($diffPath, $this->diff->betweenFiles($oldPath, $newPath, 'old.phpt', 'new.phpt'));
    }

    private function writeDiff(string $diffPath, string $diff): void
    {
        $this->files->write($diffPath, $diff, 'fixture diff');
    }

    private function deletePair(string $newPath, string $diffPath): void
    {
        $this->files->deleteFileIfExists($newPath, 'fixture');
        $this->files->deleteFileIfExists($diffPath, 'fixture diff');
    }

    private function fail(FixtureValidationResult $result, string $message, bool $failFast): void
    {
        $result->fail($message);

        if ($failFast) {
            throw new \RuntimeException($message);
        }
    }
}
