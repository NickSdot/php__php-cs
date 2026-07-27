<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle;

use InternalsCS\Fixer;
use InternalsCS\SourceFile;
use InternalsCS\Support\Whitespace;

use function array_fill;
use function array_keys;
use function array_map;
use function array_slice;
use function array_unique;
use function array_values;
use function count;
use function implode;
use function mb_strlen;
use function mb_substr;
use function mb_trim;
use function preg_match;
use function preg_split;
use function sort;
use function str_ends_with;
use function str_starts_with;

abstract class PhptFixer implements Fixer
{
    protected ?PhptFile $file = null;

    /** @var list<int> */
    private array $lines = [];

    private ?string $failureReason = null;

    private ?string $expectedUpdateFailure = null;

    final public function supports(SourceFile $file): bool
    {
        return str_ends_with($file->path, '.phpt');
    }

    final public function collect(SourceFile $file): bool
    {
        $this->file = new PhptFile($file->path, $file->rootDir);

        return $this->planPhptRewrite();
    }

    final public function persist(): bool
    {
        return self::persistBatch([$this])[0];
    }

    /**
     * @param list<self> $fixers
     *
     * @return list<bool>
     */
    final public static function persistBatch(array $fixers): array
    {
        $results = array_fill(0, count($fixers), false);
        $pending = [];

        foreach ($fixers as $index => $fixer) {
            if (!$fixer->hasPlannedRewrite()) {
                $fixer->fail('internal error: collect() did not prepare a rewrite');
                continue;
            }

            $pending[$index] = $fixer;
        }

        $runner = new PhptBatchRunner();
        $initialRuns = $runner->run(array_map(
            static fn(self $fixer): PhptFile => $fixer->phptFile(),
            array_values($pending),
        ));

        foreach (array_keys($pending) as $offset => $index) {
            $fixer = $pending[$index];
            $initial = $initialRuns[$offset];

            if ('PASS' !== $initial['status']) {
                $fixer->fail('original test did not pass (' . $fixer->runSummary($initial) . ')');
                unset($pending[$index]);
                continue;
            }

            $fixer->apply();
            $fixer->phptFile()->save();
        }

        $rewrittenRuns = $runner->run(array_map(
            static fn(self $fixer): PhptFile => $fixer->phptFile(),
            array_values($pending),
        ));
        $verify = [];

        foreach (array_keys($pending) as $offset => $index) {
            $fixer = $pending[$index];
            $file = $fixer->phptFile();
            $run = $rewrittenRuns[$offset];

            if ('PASS' === $run['status']) {
                $file->cleanupArtifacts();
                $results[$index] = true;
                continue;
            }

            if (!$fixer->changesOutput()) {
                $fixer->fail('rewritten test did not pass after a style-only rewrite (' . $fixer->runSummary($run) . ')');
                continue;
            }

            $actual = $file->readActualOutput();
            if (null === $actual) {
                $fixer->fail('rewritten test did not pass and no .out file was produced (' . $fixer->runSummary($run) . ')');
                continue;
            }

            $expectedSection = $file->expectedSectionName();
            if (null === $expectedSection) {
                $fixer->fail('no expected output section is available for update');
                continue;
            }

            $expected = $file->getSection($expectedSection);
            if (null === $expected) {
                $fixer->fail("$expectedSection section disappeared while updating expected output");
                continue;
            }

            $fixer->expectedUpdateFailure = null;
            $updated = $fixer->updateExpectedOutput($expectedSection, $expected, $actual);
            if (null === $updated) {
                $fixer->fail(
                    "$expectedSection update was not provable after rewritten test failed ("
                        . $fixer->runSummary($run) . '): '
                        . ($fixer->expectedUpdateFailure ?? 'actual output was not a safe expected-output rewrite')
                );
                continue;
            }

            $file->setExpectedSection($expectedSection, $updated);
            $file->save();
            $verify[$index] = $fixer;
        }

        $verifiedRuns = $runner->run(array_map(
            static fn(self $fixer): PhptFile => $fixer->phptFile(),
            array_values($verify),
        ));

        foreach (array_keys($verify) as $offset => $index) {
            $fixer = $verify[$index];
            $verified = $verifiedRuns[$offset];

            if ('PASS' !== $verified['status']) {
                $fixer->fail('updated expected output did not pass verification (' . $fixer->runSummary($verified) . ')');
                continue;
            }

            $fixer->phptFile()->cleanupArtifacts();
            $results[$index] = true;
        }

        return array_values($results);
    }

    public function cleanup(): void
    {
        $this->file?->cleanupArtifacts();
    }

    public function location(): string
    {
        if ([] === $this->lines) {
            return '';
        }

        $visibleLines = array_slice($this->lines, 0, 5);
        $label = 1 === count($this->lines) ? 'FILE line ' : 'FILE lines ';
        $location = $label . implode(', ', $visibleLines);
        if (count($this->lines) > count($visibleLines)) {
            $location .= ' +' . (count($this->lines) - count($visibleLines)) . ' more';
        }

        return $location;
    }

    public function failureReason(): string
    {
        return $this->failureReason ?? 'unknown reason';
    }

    abstract protected function planPhptRewrite(): bool;

    abstract protected function apply(): void;

    abstract protected function hasPlannedRewrite(): bool;

    protected function changesOutput(): bool
    {
        return false;
    }

    protected function updateExpectedOutput(string $section, string $expected, string $actual): ?string
    {
        $this->setExpectedUpdateFailure("fixer does not support updating $section");
        return null;
    }

    protected function resetDiagnostics(): void
    {
        $this->lines = [];
        $this->failureReason = null;
        $this->expectedUpdateFailure = null;
    }

    protected function markLine(int $line): void
    {
        $this->lines[] = $line;
        sort($this->lines);
        $this->lines = array_values(array_unique($this->lines));
    }

    protected function setExpectedUpdateFailure(string $reason): void
    {
        $this->expectedUpdateFailure = $reason;
    }

    protected function fail(string $reason): bool
    {
        $this->failureReason = $reason;
        return false;
    }

    /** @param array{status: string, output: string, exitCode: int} $run */
    protected function runSummary(array $run): string
    {
        $status = $run['status'];
        if (1 === preg_match('/^SKIP .* reason: (.+)$/m', $run['output'], $matches)) {
            return $status . ': ' . $this->shorten(mb_trim($matches[1]));
        }
        if (1 === preg_match('/^(PASS|SKIP|FAIL|BORK|WARN|XFAIL|XLEAK|LEAK) .+$/m', $run['output'], $matches)) {
            return $this->shorten(mb_trim($matches[0]));
        }

        $lines = preg_split('/\R/', $run['output']);

        if (false === $lines) {
            $lines = [];
        }

        foreach ($lines as $line) {
            $line = mb_trim($line);
            if ('' !== $line && !str_starts_with($line, '=')) {
                return $status . ': ' . $this->shorten($line);
            }
        }

        return $status . ', exit code ' . $run['exitCode'];
    }

    protected function shorten(string $text): string
    {
        if (mb_strlen($text = Whitespace::lineBreaksAndTabsToSpaces($text)) <= 180) {
            return $text;
        }

        return mb_substr($text, 0, 177) . '...';
    }

    protected function phptFile(): PhptFile
    {
        return $this->file ?? throw new \RuntimeException('PHPT fixer did not receive a source file');
    }
}
