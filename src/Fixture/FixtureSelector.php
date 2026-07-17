<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use function array_values;
use function ksort;

final readonly class FixtureSelector
{
    /**
     * @param list<FixtureCandidate> $candidates
     * @param (callable(FixtureSource): bool)|null $canSelect
     */
    public function select(array $candidates, ?callable $canSelect = null): FixtureSelection
    {
        $candidatesBySource = $this->candidatesBySource($candidates);
        $candidatesByFlavour = $this->candidatesByFlavour($candidates);
        $fixtures = $this->selectFixtures($candidatesBySource, $candidatesByFlavour, $canSelect);

        ksort($candidatesByFlavour);

        return new FixtureSelection(
            fixtures: array_values($fixtures),
            flavours: $candidatesByFlavour,
        );
    }

    /**
     * @param array<string, non-empty-list<FixtureCandidate>> $candidatesBySource
     * @param array<string, list<FixtureCandidate>> $candidatesByFlavour
     * @param (callable(FixtureSource): bool)|null $canSelect
     *
     * @return array<string, FixtureSource>
     */
    private function selectFixtures(array $candidatesBySource, array $candidatesByFlavour, ?callable $canSelect): array
    {
        $fixtures = [];
        $coveredFlavours = [];
        $rejectedSources = [];

        foreach ($candidatesByFlavour as $flavourKey => $flavourCandidates) {
            if (isset($coveredFlavours[$flavourKey])) {
                continue;
            }

            $fixture = $this->firstSelectableFixture(
                candidates: $flavourCandidates,
                candidatesBySource: $candidatesBySource,
                canSelect: $canSelect,
                rejectedSources: $rejectedSources,
            );

            if (null === $fixture) {
                continue;
            }

            $fixtures[$fixture->relativePath] = $fixture;
            $this->markCovered($coveredFlavours, $fixture);
        }

        return $fixtures;
    }

    /**
     * @param list<FixtureCandidate> $candidates
     * @param array<string, non-empty-list<FixtureCandidate>> $candidatesBySource
     * @param (callable(FixtureSource): bool)|null $canSelect
     * @param array<string, true> $rejectedSources
     */
    private function firstSelectableFixture(
        array $candidates,
        array $candidatesBySource,
        ?callable $canSelect,
        array &$rejectedSources,
    ): ?FixtureSource {
        foreach ($candidates as $candidate) {
            $relativePath = $candidate->relativePath();

            if (isset($rejectedSources[$relativePath])) {
                continue;
            }

            $fixture = new FixtureSource($candidatesBySource[$relativePath]);

            if (null === $canSelect || $canSelect($fixture)) {
                return $fixture;
            }

            $rejectedSources[$relativePath] = true;
        }

        return null;
    }

    /** @param array<string, true> $coveredFlavours */
    private function markCovered(array &$coveredFlavours, FixtureSource $fixture): void
    {
        foreach ($fixture->flavourKeys() as $flavourKey) {
            $coveredFlavours[$flavourKey] = true;
        }
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

        return $groups;
    }
}
