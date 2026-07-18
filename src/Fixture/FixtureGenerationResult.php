<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

final class FixtureGenerationResult
{
    /** @var list<string> */
    public array $failures = [];

    /** @var list<string> */
    public array $warnings = [];

    public bool $refreshOnly = false;

    public bool $discoveryReportsWritten = false;

    public int $scannedFiles = 0;

    public int $candidateFiles = 0;

    public int $candidateWindows = 0;

    public int $candidateFlavours = 0;

    public int $duplicateCandidates = 0;

    public int $selectedFixtures = 0;

    public bool $dryRun = true;

    public int $createdOld = 0;

    public int $verifiedPairs = 0;

    public int $updatedPairs = 0;

    public int $stalePairs = 0;

    public int $oldOnly = 0;

    /** @var list<string> */
    public array $updatedPairCases = [];

    /** @var list<string> */
    public array $stalePairCases = [];

    /** @var list<string> */
    public array $oldOnlyCases = [];

    public function failed(): bool
    {
        return [] !== $this->failures;
    }

    public function fail(string $message): void
    {
        $this->failures[] = $message;
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }
}
