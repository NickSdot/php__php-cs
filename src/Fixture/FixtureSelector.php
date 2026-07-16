<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use function array_values;
use function ksort;

final readonly class FixtureSelector
{
    /** @param list<FixtureCandidate> $candidates */
    public function select(array $candidates): FixtureSelection
    {
        $candidatesBySource = $this->candidatesBySource($candidates);
        $candidatesByFlavour = $this->candidatesByFlavour($candidates);
        $fixtures = [];
        $coveredFlavours = [];

        foreach ($candidates as $candidate) {
            if (isset($coveredFlavours[$candidate->fixtureKey()])) {
                continue;
            }

            $fixture = new FixtureSource($candidatesBySource[$candidate->relativePath()]);
            $fixtures[$candidate->relativePath()] = $fixture;

            foreach ($fixture->flavourKeys() as $flavourKey) {
                $coveredFlavours[$flavourKey] = true;
            }
        }

        return new FixtureSelection(
            fixtures: array_values($fixtures),
            flavours: $candidatesByFlavour,
        );
    }

    /**
     * @param list<FixtureCandidate> $candidates
     *
     * @return array<string, non-empty-list<FixtureCandidate>>
     */
    private function candidatesBySource(array $candidates): array
    {
        $groups = [];

        foreach ($candidates as $candidate) {
            $groups[$candidate->relativePath()][] = $candidate;
        }

        return $groups;
    }

    /**
     * @param list<FixtureCandidate> $candidates
     *
     * @return array<string, list<FixtureCandidate>>
     */
    private function candidatesByFlavour(array $candidates): array
    {
        $groups = [];

        foreach ($candidates as $candidate) {
            $groups[$candidate->fixtureKey()][] = $candidate;
        }

        ksort($groups);

        return $groups;
    }
}
