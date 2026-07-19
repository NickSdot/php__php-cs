<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

final readonly class FixtureGenerationRun
{
    public function __construct(
        public string $fixer,
        public string $fixturesDir,
        public ?string $reportsDir,
        public FixtureGenerationResult $result,
    ) {}
}
