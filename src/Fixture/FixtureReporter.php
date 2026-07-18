<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

interface FixtureReporter
{
    /** @param array<string, FixtureWriteResult> $writeResults */
    public function write(
        string $reportsDir,
        string $fixturesDir,
        FixtureGenerationResult $result,
        FixtureSelection $selection,
        array $writeResults,
    ): void;

    public function writeRefresh(string $reportsDir, string $fixturesDir, FixtureGenerationResult $result): void;
}
