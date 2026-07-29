<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use function ksort;
use function str_ends_with;

final readonly class FixtureSelector
{
    /**
     * @param list<FixtureCandidate> $candidates
     * @param (callable(FixtureSource): bool)|null $canSelect
     * @param list<FixtureCandidate> $fixtureCandidates
     */
    public function select(array $candidates, ?callable $canSelect = null, bool $splitSourceCandidatesByFixtureCase = false, array $fixtureCandidates = []): FixtureSelection
    {
        $selectionCandidates = [...$candidates, ...$fixtureCandidates];
        $candidatesBySource = $this->candidatesBySource($selectionCandidates, $splitSourceCandidatesByFixtureCase);
        $candidatesByFlavour = $this->candidatesByFlavour($candidates);
        $candidatesByFixtureCase = $this->candidatesByFixtureCase($candidates, $fixtureCandidates);
        $fixtures = $this->selectFixtures($candidatesBySource, $candidatesByFixtureCase, $canSelect, $splitSourceCandidatesByFixtureCase);

        ksort($candidatesByFlavour);

        return new FixtureSelection(
            fixtures: $fixtures,
            flavours: $candidatesByFlavour,
        );
    }

    /**
     * @param array<string, list<FixtureCandidate>> $candidatesBySource
     * @param array<string, list<FixtureCandidate>> $candidatesByFixtureCase
     * @param (callable(FixtureSource): bool)|null $canSelect
     *
     * @return list<FixtureSource>
     */
    private function selectFixtures(array $candidatesBySource, array $candidatesByFixtureCase, ?callable $canSelect, bool $splitSourceCandidatesByFixtureCase): array
    {
        $fixtures = [];
        $coveredFlavours = [];
        $rejectedSources = [];

        foreach ($candidatesByFixtureCase as $caseCandidates) {
            if ($this->allFlavoursCovered($coveredFlavours, $caseCandidates)) {
                continue;
            }

            $fixture = $this->firstSelectableFixture(
                candidates: $caseCandidates,
                candidatesBySource: $candidatesBySource,
                canSelect: $canSelect,
                rejectedSources: $rejectedSources,
                splitSourceCandidatesByFixtureCase: $splitSourceCandidatesByFixtureCase,
            );

            if (null === $fixture) {
                continue;
            }

            $fixtures[] = $fixture;
            $this->markCovered($coveredFlavours, $fixture);
        }

        return $fixtures;
    }

    /**
     * @param array<string, true> $coveredFlavours
     * @param list<FixtureCandidate> $candidates
     */
    private function allFlavoursCovered(array $coveredFlavours, array $candidates): bool
    {
        return array_all($candidates, fn($candidate) => isset($coveredFlavours[$candidate->fixtureKey]));
    }

    /**
     * @param list<FixtureCandidate> $candidates
     * @param array<string, list<FixtureCandidate>> $candidatesBySource
     * @param (callable(FixtureSource): bool)|null $canSelect
     * @param array<string, true> $rejectedSources
     */
    private function firstSelectableFixture(array $candidates, array $candidatesBySource, ?callable $canSelect, array &$rejectedSources, bool $splitSourceCandidatesByFixtureCase): ?FixtureSource
    {
        foreach ($candidates as $candidate) {
            $sourceKey = $this->sourceKey($candidate, $splitSourceCandidatesByFixtureCase);

            if (isset($rejectedSources[$sourceKey])) {
                continue;
            }

            $sourceCandidates = $candidatesBySource[$sourceKey] ?? [];

            if ([] === $sourceCandidates) {
                continue;
            }

            $fixture = new FixtureSource(
                candidates: $sourceCandidates,
                coveredCandidates: [] === $candidates ? null : $candidates,
            );

            if (null === $canSelect || $canSelect($fixture)) {
                return $fixture;
            }

            $rejectedSources[$sourceKey] = true;
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
     * @return array<string, list<FixtureCandidate>>
     */
    private function candidatesBySource(array $candidates, bool $splitSourceCandidatesByFixtureCase): array
    {
        $groups = [];

        foreach ($candidates as $candidate) {
            $groups[$this->sourceKey($candidate, $splitSourceCandidatesByFixtureCase)][] = $candidate;
        }

        return $groups;
    }

    private function sourceKey(FixtureCandidate $candidate, bool $splitSourceCandidatesByFixtureCase): string
    {
        if (!$splitSourceCandidatesByFixtureCase || $this->isFixtureCandidate($candidate)) {
            return $candidate->relativePath;
        }

        return $candidate->relativePath . "\0" . $candidate->fixtureCaseKey;
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
            $groups[$candidate->fixtureKey][] = $candidate;
        }

        foreach ($groups as $flavour => $group) {
            $groups[$flavour] = $this->withFixtureCandidatesFirst($group);
        }

        return $groups;
    }

    /**
     * @param list<FixtureCandidate> $candidates
     * @param list<FixtureCandidate> $fixtureCandidates
     *
     * @return array<string, list<FixtureCandidate>>
     */
    private function candidatesByFixtureCase(array $candidates, array $fixtureCandidates): array
    {
        $groups = [];

        foreach ($candidates as $candidate) {
            $groups[$candidate->fixtureCaseKey][] = $candidate;
        }

        foreach ($fixtureCandidates as $candidate) {
            if (!isset($groups[$candidate->fixtureCaseKey])) {
                continue;
            }

            $groups[$candidate->fixtureCaseKey][] = $candidate;
        }

        foreach ($groups as $case => $group) {
            $groups[$case] = $this->withFixtureCandidatesFirst($group);
        }

        ksort($groups);

        return $groups;
    }

    /**
     * @param list<FixtureCandidate> $candidates
     * @return list<FixtureCandidate>
     */
    private function withFixtureCandidatesFirst(array $candidates): array
    {
        $fixtures = [];
        $sources = [];

        foreach ($candidates as $candidate) {
            if ($this->isFixtureCandidate($candidate)) {
                $fixtures[] = $candidate;
                continue;
            }

            $sources[] = $candidate;
        }

        if ([] === $fixtures) {
            return $sources;
        }

        if ([] === $sources) {
            return $fixtures;
        }

        return [...$fixtures, ...$sources];
    }

    private function isFixtureCandidate(FixtureCandidate $candidate): bool
    {
        return str_ends_with($candidate->relativePath, '/old.phpt');
    }
}
