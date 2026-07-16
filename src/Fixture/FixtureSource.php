<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use function array_any;
use function array_keys;

final readonly class FixtureSource
{
    public string $sourcePath;

    public string $relativePath;

    /** @param non-empty-list<FixtureCandidate> $candidates */
    public function __construct(
        public array $candidates,
    ) {
        $first = $candidates[0] ?? throw new \InvalidArgumentException('Fixture source requires at least one candidate');

        $this->sourcePath = $first->sourcePath();
        $this->relativePath = $first->relativePath();
    }

    public function firstCandidate(): FixtureCandidate
    {
        return $this->candidates[0];
    }

    /** @return list<string> */
    public function flavourKeys(): array
    {
        $keys = [];

        foreach ($this->candidates as $candidate) {
            $keys[$candidate->fixtureKey()] = true;
        }

        return array_keys($keys);
    }

    public function covers(string $flavourKey): bool
    {
        return array_any($this->candidates, fn(FixtureCandidate $candidate): bool => $candidate->fixtureKey() === $flavourKey);
    }
}
