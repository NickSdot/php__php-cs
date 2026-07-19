<?php

declare(strict_types=1);

namespace InternalsCS;

use InternalsCS\Support\FileSystem;
use InternalsCS\Support\MarkdownTable;

use function array_map;
use function basename;
use function implode;
use function preg_replace;

final readonly class FixRunReportWriter
{
    public function __construct(
        private FileSystem $files = new FileSystem(),
        private MarkdownTable $table = new MarkdownTable(),
    ) {}

    /**
     * @param list<string> $targets
     * @param list<string> $fixers
     */
    public function write(
        string $reportDir,
        \DateTimeImmutable $timestamp,
        string $phpSrcDir,
        array $targets,
        array $fixers,
        FixRunResult $result,
    ): string {
        $this->files->ensureDirectory($reportDir, 'fix run report directory');

        $path = $reportDir . DIRECTORY_SEPARATOR . $this->fileName($timestamp, $targets);
        $this->files->write(
            $path,
            $this->render($timestamp, $phpSrcDir, $targets, $fixers, $result),
            'fix run report',
        );

        return $path;
    }

    /**
     * @param list<string> $targets
     * @param list<string> $fixers
     */
    private function render(
        \DateTimeImmutable $timestamp,
        string $phpSrcDir,
        array $targets,
        array $fixers,
        FixRunResult $result,
    ): string {
        return implode("\n", [
            '# Fix Run',
            '',
            '## Summary',
            ...$this->table->render(
                ['Metric', 'Value'],
                [
                    ['Timestamp', $timestamp->format(\DateTimeInterface::ATOM)],
                    ['Mode', $result->check ? 'check' : 'write'],
                    ['PHP source', $phpSrcDir],
                    ['Targets', $this->listValue($targets, 'all')],
                    ['Fixers', $this->listValue($fixers, 'all')],
                    ['Scanned files', $result->scannedFiles],
                    ['Changed candidates', $result->changed()],
                    ['Fixed', $result->fixed()],
                    ['Skipped', $result->skipped()],
                    ['Needs changes', $result->needsChanges()],
                ],
            ),
            '## Skips',
            ...$this->entryTable($result->entriesWithStatus(FixRunStatus::Skipped), includeReason: true),
            '## Needs Changes',
            ...$this->entryTable($result->entriesWithStatus(FixRunStatus::NeedsChanges), includeReason: false),
            '## Fixes',
            ...$this->entryTable($result->entriesWithStatus(FixRunStatus::Fixed), includeReason: false),
        ]) . "\n";
    }

    /** @param list<string> $targets */
    private function fileName(\DateTimeImmutable $timestamp, array $targets): string
    {
        return $timestamp->format('Ymd-His-u') . '-' . $this->targetSlug($targets) . '.md';
    }

    /** @param list<string> $targets */
    private function targetSlug(array $targets): string
    {
        if ([] === $targets) {
            return 'all';
        }

        $names = array_map(basename(...), $targets);
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', implode('-', $names));

        return $slug ?? 'targets';
    }

    /** @param list<string> $values */
    private function listValue(array $values, string $empty): string
    {
        return [] === $values ? $empty : implode(', ', $values);
    }

    /**
     * @param list<FixRunEntry> $entries
     * @return list<string>
     */
    private function entryTable(array $entries, bool $includeReason): array
    {
        if ([] === $entries) {
            return ['', '- none', ''];
        }

        $headers = $includeReason
            ? ['File', 'Fixer', 'Location', 'Reason']
            : ['File', 'Fixer', 'Location'];
        $rows = [];

        foreach ($entries as $entry) {
            $row = [$entry->file, $entry->fixer, $entry->location];

            if ($includeReason) {
                $row[] = $entry->reason ?? '';
            }

            $rows[] = $row;
        }

        return $this->table->render($headers, $rows);
    }
}
