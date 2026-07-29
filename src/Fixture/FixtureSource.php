<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use function array_keys;

final readonly class FixtureSource
{
    public string $sourcePath;

    public string $relativePath;

    /** @var non-empty-list<FixtureCandidate> */
    public array $coveredCandidates;

    /**
     * @param non-empty-list<FixtureCandidate> $candidates
     * @param non-empty-list<FixtureCandidate>|null $coveredCandidates
     */
    public function __construct(
        public array $candidates,
        ?array $coveredCandidates = null,
    ) {
        $first = $candidates[0];

        $this->sourcePath = $first->sourcePath;
        $this->relativePath = $first->relativePath;
        $this->coveredCandidates = $coveredCandidates ?? $candidates;
    }

    public function firstCandidate(): FixtureCandidate
    {
        return $this->candidates[0];
    }

    /** @return list<string> */
    public function flavourKeys(): array
    {
        $keys = [];

        foreach ($this->coveredCandidates as $candidate) {
            $keys[$candidate->fixtureKey] = true;
        }

        return array_keys($keys);
    }

    /** @return list<string> */
    public function fixtureCaseKeys(): array
    {
        $keys = [];

        foreach ($this->coveredCandidates as $candidate) {
            $keys[$candidate->fixtureCaseKey] = true;
        }

        return array_keys($keys);
    }
}
