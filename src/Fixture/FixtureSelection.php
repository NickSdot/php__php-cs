<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use function count;

final readonly class FixtureSelection
{
    /**
     * @param list<FixtureSource> $fixtures
     * @param array<string, list<FixtureCandidate>> $flavours
     */
    public function __construct(
        public array $fixtures,
        public array $flavours,
    ) {}

    public function fixtureCount(): int
    {
        return count($this->fixtures);
    }

    public function flavourCount(): int
    {
        return count($this->flavours);
    }

    public function duplicateCandidateWindows(int $candidateWindows): int
    {
        return $candidateWindows - $this->flavourCount();
    }

    /** @return array<string, FixtureSource> */
    public function fixtureByFlavour(): array
    {
        $fixtures = [];

        foreach ($this->fixtures as $fixture) {
            foreach ($fixture->flavourKeys() as $flavourKey) {
                $fixtures[$flavourKey] ??= $fixture;
            }
        }

        return $fixtures;
    }
}
