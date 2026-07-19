<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use InternalsCS\Support\FileSystem;
use InternalsCS\Support\MarkdownTable;
use InternalsCS\Support\Paths;

use function count;
use function dirname;
use function implode;

final readonly class FixtureGenerationRunReporter
{
    public function __construct(
        private FileSystem $files = new FileSystem(),
        private MarkdownTable $table = new MarkdownTable(),
        private Paths $paths = new Paths(),
    ) {}

    /** @param list<FixtureGenerationRun> $runs */
    public function write(string $reportsRoot, array $runs, int $sourceFiles): void
    {
        $this->files->ensureDirectory($reportsRoot, 'reports directory');
        $this->files->write($reportsRoot . '/fixture_generation.md', $this->render($runs, $sourceFiles, $reportsRoot, dirname($reportsRoot)), 'fixture generation report');
    }

    /** @param list<FixtureGenerationRun> $runs */
    private function render(array $runs, int $sourceFiles, string $reportsRoot, string $baseDir): string
    {
        return implode("\n", [
            '# Fixture generation',
            '',
            '## Source',
            ...$this->table->render(
                ['Metric', 'Value'],
                [
                    ['Scanned source files once', $sourceFiles],
                ],
            ),
            '## Run',
            ...$this->table->render(
                ['Fixer', 'Input files', 'Flavours', 'Selected', 'Created old', 'Verified', 'Updated', 'Stale', 'Old-only', 'Failures'],
                $this->runRows($runs),
            ),
            '## Details',
            ...$this->table->render(
                ['Fixer', 'Fixtures', 'Reports'],
                $this->detailRows($runs, $reportsRoot, $baseDir),
            ),
            '## Failures',
            ...$this->failureLines($runs),
        ]) . "\n";
    }

    /**
     * @param list<FixtureGenerationRun> $runs
     * @return list<list<int|string>>
     */
    private function runRows(array $runs): array
    {
        $rows = [];

        foreach ($runs as $run) {
            $result = $run->result;

            $rows[] = [
                $run->fixer,
                $result->scannedFiles,
                $result->candidateFlavours,
                $result->selectedFixtures,
                $result->createdOld,
                $result->verifiedPairs,
                $result->updatedPairs,
                $result->stalePairs,
                $result->oldOnly,
                count($result->failures),
            ];
        }

        return $rows;
    }

    /**
     * @param list<FixtureGenerationRun> $runs
     * @return list<list<string>>
     */
    private function detailRows(array $runs, string $reportsRoot, string $baseDir): array
    {
        $rows = [];

        foreach ($runs as $run) {
            $rows[] = [
                $run->fixer,
                $this->paths->relative($run->fixturesDir, $baseDir),
                $this->paths->relative($run->reportsDir ?? $reportsRoot . '/fixture_generation.md', $baseDir),
            ];
        }

        return $rows;
    }

    /**
     * @param list<FixtureGenerationRun> $runs
     * @return list<string>
     */
    private function failureLines(array $runs): array
    {
        $lines = [];

        foreach ($runs as $run) {
            foreach ($run->result->failures as $failure) {
                $lines[] = '- ' . $run->fixer . ': ' . $failure;
            }
        }

        return [] === $lines ? ['- none'] : $lines;
    }
}
