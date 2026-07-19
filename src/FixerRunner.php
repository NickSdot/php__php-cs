<?php

declare(strict_types=1);

namespace InternalsCS;

use function array_unique;
use function array_values;
use function count;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function preg_match;
use function realpath;
use function sort;
use function str_starts_with;

final readonly class FixerRunner
{
    /** @param list<class-string<Fixer>> $fixerClasses */
    public function __construct(private string $rootDir, private array $fixerClasses) {}

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    public function collectFiles(array $paths): array
    {
        if ([] === $paths) {
            $paths = [ $this->rootDir ];
        }

        $files = [];
        foreach ($paths as $path) {
            if (!$this->isAbsolutePath($path)) {
                $path = $this->rootDir . DIRECTORY_SEPARATOR . $path;
            }
            if (is_file($path)) {
                $realPath = realpath($path);
                $files[] = false === $realPath ? $path : $realPath;
                continue;
            }
            if (!is_dir($path)) {
                throw new \InvalidArgumentException("Path does not exist: $path");
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {

                if (!$file instanceof \SplFileInfo) {
                    continue;
                }

                if ($file->isFile()) {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);
        return array_values(array_unique($files));
    }

    /**
     * @param list<string> $files
     * @param null|\Closure(FixRunEntry): void $onEntry
     */
    public function run(array $files, bool $check, ?\Closure $onEntry = null): FixRunResult
    {
        $result = new FixRunResult(count($files), $check);

        foreach ($files as $path) {
            $source = new SourceFile($path, $this->rootDir);

            foreach ($this->fixerClasses as $fixerClass) {
                $fixer = new $fixerClass();

                if (!$fixer->supports($source)) {
                    continue;
                }

                if (!$fixer->collect($source)) {
                    continue;
                }

                if ($check) {
                    $entry = new FixRunEntry(
                        status: FixRunStatus::NeedsChanges,
                        file: $source->relativePath(),
                        fixer: $fixer->name(),
                        location: $fixer->location(),
                    );
                    $this->record($result, $entry, $onEntry);
                    continue;
                }

                if ($fixer->persist()) {
                    $entry = new FixRunEntry(
                        status: FixRunStatus::Fixed,
                        file: $source->relativePath(),
                        fixer: $fixer->name(),
                        location: $fixer->location(),
                    );
                    $this->record($result, $entry, $onEntry);
                } else {
                    $reason = $this->fixerFailureReason($fixer);
                    $entry = new FixRunEntry(
                        status: FixRunStatus::Skipped,
                        file: $source->relativePath(),
                        fixer: $fixer->name(),
                        location: $fixer->location(),
                        reason: $reason,
                    );
                    $this->record($result, $entry, $onEntry);
                    $source->restoreOriginal();
                    $fixer->cleanup();
                    continue;
                }

                $source = new SourceFile($path, $this->rootDir);
            }
        }

        return $result;
    }

    /** @param null|\Closure(FixRunEntry): void $onEntry */
    private function record(FixRunResult $result, FixRunEntry $entry, ?\Closure $onEntry): void
    {
        $result->add($entry);
        $onEntry?->__invoke($entry);
    }

    /**
     * @param list<string> $files
     *
     * @return array{changed: bool, failed: bool, output: string, failure: string|null}
     */
    public function print(array $files): array
    {
        if (1 !== count($files)) {
            throw new \InvalidArgumentException('--print requires exactly one PHPT file');
        }

        return $this->printFile($files[0]);
    }

    /** @return array{changed: bool, failed: bool, output: string, failure: string|null} */
    public function printFile(string $path): array
    {
        $original = file_get_contents($path);
        if (false === $original) {
            throw new \RuntimeException("Cannot read $path");
        }

        $source = new SourceFile($path, $this->rootDir);
        $lastFixer = null;
        $changed = false;

        try {
            foreach ($this->fixerClasses as $fixerClass) {
                $fixer = new $fixerClass();

                if (!$fixer->supports($source)) {
                    continue;
                }

                if (!$fixer->collect($source)) {
                    continue;
                }

                $changed = true;
                $lastFixer = $fixer;

                if (!$fixer->persist()) {
                    return [
                        'changed' => true,
                        'failed' => true,
                        'output' => $original,
                        'failure' => $this->fixerFailureReason($fixer),
                    ];
                }

                $source = new SourceFile($path, $this->rootDir);
            }

            $output = file_get_contents($path);
            if (false === $output) {
                throw new \RuntimeException("Cannot read rewritten $path");
            }

            return [
                'changed' => $changed,
                'failed' => false,
                'output' => $output,
                'failure' => null,
            ];
        } finally {
            if (false === file_put_contents($path, $original)) {
                throw new \RuntimeException("Cannot restore $path");
            }

            $lastFixer?->cleanup();
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || 1 === preg_match('/^[A-Za-z]:[\/\\\\]/', $path);
    }

    private function fixerFailureReason(Fixer $fixer): ?string
    {
        $reason = $fixer->failureReason();
        return '' === $reason ? null : $reason;
    }
}
