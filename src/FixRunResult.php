<?php

declare(strict_types=1);

namespace InternalsCS;

use function count;

final class FixRunResult
{
    /** @var list<FixRunEntry> */
    private array $entries = [];

    public function __construct(
        public readonly int $scannedFiles,
        public readonly bool $check,
    ) {}

    public function add(FixRunEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    public function changed(): int
    {
        return count($this->entries);
    }

    public function fixed(): int
    {
        return $this->count(FixRunStatus::Fixed);
    }

    public function skipped(): int
    {
        return $this->count(FixRunStatus::Skipped);
    }

    public function needsChanges(): int
    {
        return $this->count(FixRunStatus::NeedsChanges);
    }

    /** @return list<FixRunEntry> */
    public function entriesWithStatus(FixRunStatus $status): array
    {
        $entries = [];

        foreach ($this->entries as $entry) {
            if ($entry->status === $status) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    private function count(FixRunStatus $status): int
    {
        return count($this->entriesWithStatus($status));
    }
}
