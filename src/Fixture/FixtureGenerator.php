<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use InternalsCS\SourceFinder;

use function count;

final readonly class FixtureGenerator
{
    public function __construct(
        private FixtureScanner $scanner,
        private FixtureReporter $reports,
        private SourceFinder $finder = new SourceFinder(),
        private FixtureSelector $selector = new FixtureSelector(),
        private FixtureWriter $writer = new FixtureWriter(),
        private FixtureValidator $validator = new FixtureValidator(),
    ) {}

    public function generate(FixtureGenerationOptions $options): FixtureGenerationResult
    {
        $result = new FixtureGenerationResult();
        $result->dryRun = !$options->write;

        if ($options->refreshOnly) {
            return $this->refreshOnly($result, $options);
        }

        if ($options->sourceDirty) {
            return $this->dirtySource($result, $options);
        }

        $scan = $this->scan($result, $options);
        $candidates = $scan['candidates'];
        $selection = $scan['selection'];

        if (!$options->write) {
            return $result;
        }

        $writeResults = [];

        foreach ($selection->fixtures as $fixture) {
            $write = $this->writer->write($fixture, $options->fixturesDir);
            $writeResults[$fixture->relativePath] = $write;

            if (null !== $write->failure && !$write->oldOnly) {
                $result->fail($fixture->relativePath . ': ' . $write->failure);
                continue;
            }

            $result->createdOld += $write->createdOld ? 1 : 0;
            $result->updatedPairs += $write->updatedNew ? 1 : 0;
            $result->verifiedPairs += $write->verifiedPair ? 1 : 0;
            $result->oldOnly += $write->oldOnly ? 1 : 0;
        }

        $this->refreshFixtures($result, $options, $selection);

        $this->writeDiscoveryReports($result, $options, $candidates, $selection, $writeResults);

        return $result;
    }

    private function refreshOnly(FixtureGenerationResult $result, FixtureGenerationOptions $options): FixtureGenerationResult
    {
        $result->refreshOnly = true;

        if (!$options->write) {
            return $result;
        }

        if ($options->sourceDirty) {
            $this->refreshFixtures($result, $options);
            $result->warn('source checkout is dirty; skipped source report recomputation during refresh-only run');
            $this->reports->writeRefresh($options->reportsDir, $options->fixturesDir, $result);
            return $result;
        }

        $scan = $this->scan($result, $options);
        $this->refreshFixtures($result, $options, $scan['selection']);
        $this->writeDiscoveryReports($result, $options, $scan['candidates'], $scan['selection'], []);

        return $result;
    }

    private function dirtySource(FixtureGenerationResult $result, FixtureGenerationOptions $options): FixtureGenerationResult
    {
        $result->refreshOnly = true;
        $result->warn('source checkout is dirty; skipped source discovery and old.phpt import; pass --allow-dirty to generate from dirty source');

        if (!$options->write) {
            return $result;
        }

        $this->refreshFixtures($result, $options);
        $this->reports->writeRefresh($options->reportsDir, $options->fixturesDir, $result);

        return $result;
    }

    /** @return array{candidates: list<FixtureCandidate>, selection: FixtureSelection} */
    private function scan(FixtureGenerationResult $result, FixtureGenerationOptions $options): array
    {
        $files = $this->finder->find(
            rootDir: $options->sourceRoot,
            scanPaths: $options->paths,
            excludedRoots: $options->excludedRoots,
            extensions: $options->extensions,
        );
        $candidates = $this->scanner->scan($files, $options->sourceRoot);
        $selection = $this->selector->select($candidates);

        $result->scannedFiles = count($files);
        $result->candidateFiles = $this->candidateFileCount($candidates);
        $result->candidateWindows = count($candidates);
        $result->candidateFlavours = $selection->flavourCount();
        $result->duplicateCandidates = $selection->duplicateCandidateWindows(count($candidates));
        $result->selectedFixtures = $selection->fixtureCount();

        return [
            'candidates' => $candidates,
            'selection' => $selection,
        ];
    }

    private function refreshFixtures(
        FixtureGenerationResult $result,
        FixtureGenerationOptions $options,
        ?FixtureSelection $selection = null,
    ): void {
        $validation = $this->validator->validate(new FixtureValidationOptions(
            fixturesDir: $options->fixturesDir,
            cases: [],
            runner: $options->runner,
            update: true,
            failFast: false,
            refreshPairs: true,
        ));

        foreach ($validation->failures as $failure) {
            $result->fail($failure);
        }

        $result->verifiedPairs = $validation->handled;
        $result->updatedPairs = $validation->updated;
        $result->deletedPairs = $validation->deletedPairs;
        $result->stalePairs = $validation->stalePairs;
        $result->oldOnly = $validation->oldOnly;
        $result->updatedPairCases = $validation->updatedCases;
        $result->stalePairCases = $validation->staleCases;
        $result->oldOnlyCases = $validation->oldOnlyCases;
    }

    /**
     * @param list<FixtureCandidate> $candidates
     * @param array<string, FixtureWriteResult> $writeResults
     */
    private function writeDiscoveryReports(
        FixtureGenerationResult $result,
        FixtureGenerationOptions $options,
        array $candidates,
        FixtureSelection $selection,
        array $writeResults,
    ): void {
        $this->reports->write($options->reportsDir, $options->fixturesDir, $result, $candidates, $selection, $writeResults);
        $result->discoveryReportsWritten = true;
    }

    /** @param list<FixtureCandidate> $candidates */
    private function candidateFileCount(array $candidates): int
    {
        $files = [];

        foreach ($candidates as $candidate) {
            $files[$candidate->relativePath()] = true;
        }

        return count($files);
    }
}
