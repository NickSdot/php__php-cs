<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

final class FixtureValidationResult
{
    /** @var list<string> */
    public array $failures = [];

    public int $fixtures = 0;

    public int $handled = 0;

    public int $oldOnly = 0;

    public int $updated = 0;

    public int $deletedPairs = 0;

    public int $stalePairs = 0;

    /** @var list<string> */
    public array $updatedCases = [];

    /** @var list<string> */
    public array $staleCases = [];

    /** @var list<string> */
    public array $oldOnlyCases = [];

    public function fail(string $message): void
    {
        $this->failures[] = $message;
    }
}
