<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use function array_any;

final readonly class FixtureGenerationSummary
{
    /** @param list<FixtureGenerationRun> $runs */
    public function __construct(
        public int $sourceFiles,
        public array $runs,
        public ?string $reportPath,
    ) {}

    public function failed(): bool
    {
        return array_any($this->runs, fn(FixtureGenerationRun $run): bool => $run->result->failed());
    }
}
