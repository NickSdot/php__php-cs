<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use InternalsCS\SourceFile;
use InternalsCS\SourceFinder;
use InternalsCS\Support\GitStatus;

use function array_fill_keys;
use function array_keys;
use function count;
use function glob;
use function is_dir;

final readonly class FixtureGenerator
{
    public function __construct(
        private SourceFinder $sourceFinder = new SourceFinder(),
        private FixtureSelector $selector = new FixtureSelector(),
        private FixtureWriter $writer = new FixtureWriter(),
        private FixtureValidator $validator = new FixtureValidator(),
        private FixtureCaseName $caseName = new FixtureCaseName(),
        private FixtureGenerationRunReporter $reports = new FixtureGenerationRunReporter(),
        private GitStatus $git = new GitStatus(),
    ) {}

    /** @param list<FixtureDiscovery> $discoveries */
    public function generate(FixtureGenerationOptions $options, array $discoveries): FixtureGenerationSummary
    {
        $sourceDirty = !$options->allowDirty && $this->git->isDirty($options->phpSrcRoot->path);
        $sourceFiles = $sourceDirty ? [] : $this->sourceFiles($options, $discoveries);
        $candidatesByFixer = $sourceDirty
            ? $this->emptyCandidateMap($discoveries)
            : $this->candidatesByFixer($discoveries, $sourceFiles, $options->fixturesRoot);
        $sourceFileCount = count($sourceFiles);
        $runs = [];

        foreach ($discoveries as $discovery) {
            $job = new FixtureGenerationJob(
                discovery: $discovery,
                sourceDirty: $sourceDirty,
                options: $options,
                sourceFileCount: $sourceFileCount,
                candidates: $candidatesByFixer[$discovery->fixerName()],
            );

            $runs[] = new FixtureGenerationRun(
                fixer: $job->fixer,
                fixturesDir: $job->fixturesDir,
                reportsDir: $job->reportsDir,
                result: $this->generateFixtures($job),
            );
        }

        $reportPath = null;

        if ($options->write) {
            $reportPath = $options->reportsRoot . '/fixture_generation.md';
            $this->reports->write($options->reportsRoot, $runs, $sourceFileCount);
        }

        return new FixtureGenerationSummary(
            sourceFiles: $sourceFileCount,
            runs: $runs,
            reportPath: $reportPath,
        );
    }

    /**
     * @param list<FixtureDiscovery> $discoveries
     * @return list<SourceFile>
     */
    private function sourceFiles(FixtureGenerationOptions $options, array $discoveries): array
    {
        $paths = $this->sourceFinder->find(
            rootDir: $options->phpSrcRoot->path,
            scanPaths: $options->paths,
            excludedRoots: [
                $options->fixturesRoot,
            ],
            extensions: $this->sourceExtensions($discoveries),
        );

        $files = [];

        foreach ($paths as $path) {
            $files[] = new SourceFile($path, $options->phpSrcRoot->path);
        }

        return $files;
    }

    /**
     * @param list<FixtureDiscovery> $discoveries
     * @param list<SourceFile> $sourceFiles
     * @return array<string, list<FixtureCandidate>>
     */
    private function candidatesByFixer(array $discoveries, array $sourceFiles, string $fixturesRoot): array
    {
        $candidates = $this->emptyCandidateMap($discoveries);

        foreach ($sourceFiles as $sourceFile) {
            foreach ($discoveries as $discovery) {
                foreach ($discovery->candidates($sourceFile) as $candidate) {
                    $candidates[$discovery->fixerName()][] = $candidate;
                }
            }
        }

        foreach ($discoveries as $discovery) {
            foreach ($this->manualSources($discovery, $fixturesRoot) as $manualSource) {
                foreach ($discovery->candidates($manualSource) as $candidate) {
                    $candidates[$discovery->fixerName()][] = $candidate;
                }
            }
        }

        return $candidates;
    }

    /**
     * @param list<FixtureDiscovery> $discoveries
     * @return array<string, list<FixtureCandidate>>
     */
    private function emptyCandidateMap(array $discoveries): array
    {
        return array_fill_keys($this->fixerNames($discoveries), []);
    }

    /**
     * @param list<FixtureDiscovery> $discoveries
     * @return list<string>
     */
    private function fixerNames(array $discoveries): array
    {
        $names = [];

        foreach ($discoveries as $discovery) {
            $names[$discovery->fixerName()] = true;
        }

        return array_keys($names);
    }

    /** @return list<SourceFile> */
    private function manualSources(FixtureDiscovery $discovery, string $fixturesRoot): array
    {
        $fixturesDir = $discovery->fixturesDir($fixturesRoot);

        if (!is_dir($fixturesDir)) {
            return [];
        }

        $files = glob($fixturesDir . '/manual_*/old.phpt');

        if (false === $files) {
            return [];
        }

        $sources = [];

        foreach ($files as $file) {
            $sources[] = new SourceFile($file, $fixturesDir);
        }

        return $sources;
    }

    /**
     * @param list<FixtureDiscovery> $discoveries
     * @return list<string>
     */
    private function sourceExtensions(array $discoveries): array
    {
        $extensions = [];

        foreach ($discoveries as $discovery) {
            $discoveryExtensions = $discovery->sourceExtensions();

            if ([] === $discoveryExtensions) {
                return [];
            }

            foreach ($discoveryExtensions as $extension) {
                $extensions[] = $extension;
            }
        }

        return $this->sourceFinder->normaliseExtensions($extensions);
    }

    private function generateFixtures(FixtureGenerationJob $job): FixtureGenerationResult
    {
        $result = new FixtureGenerationResult();
        $result->dryRun = !$job->write;

        if ($job->refreshOnly) {
            return $this->refreshOnly($result, $job);
        }

        if ($job->sourceDirty) {
            return $this->dirtySource($result, $job);
        }

        $selection = $this->select($result, $job);

        if (!$job->write) {
            return $result;
        }

        $writeResults = [];

        foreach ($selection->fixtures as $fixture) {
            $writeResult = $this->writer->write($fixture, $job->fixturesDir);
            $writeResults[$fixture->relativePath] = $writeResult;

            if (null !== $writeResult->failure && !$writeResult->oldOnly) {
                $result->fail($fixture->relativePath . ': ' . $writeResult->failure);
                continue;
            }

            $result->createdOld += $writeResult->createdOld ? 1 : 0;
            $result->updatedPairs += $writeResult->updatedNew ? 1 : 0;
            $result->verifiedPairs += $writeResult->verifiedPair ? 1 : 0;
            $result->oldOnly += $writeResult->oldOnly ? 1 : 0;
        }

        $this->refreshFixtures($result, $job, $selection);
        $this->writeDiscoveryReports($result, $job, $selection, $writeResults);

        return $result;
    }

    private function refreshOnly(FixtureGenerationResult $result, FixtureGenerationJob $job): FixtureGenerationResult
    {
        $result->refreshOnly = true;

        if (!$job->write) {
            return $result;
        }

        if ($job->sourceDirty) {
            $this->refreshFixtures($result, $job);
            $result->warn('source checkout is dirty; skipped source report recomputation during refresh-only run');
            $this->writeRefreshReport($result, $job);
            return $result;
        }

        $selection = $this->select($result, $job);

        $this->refreshFixtures($result, $job, $selection);
        $this->writeDiscoveryReports($result, $job, $selection, []);

        return $result;
    }

    private function dirtySource(FixtureGenerationResult $result, FixtureGenerationJob $job): FixtureGenerationResult
    {
        $result->refreshOnly = true;
        $result->warn('source checkout is dirty; skipped source discovery and old.phpt import; pass --allow-dirty to generate from dirty source');

        if (!$job->write) {
            return $result;
        }

        $this->refreshFixtures($result, $job, null, withSourceContext: false);
        $this->writeRefreshReport($result, $job);

        return $result;
    }

    private function select(FixtureGenerationResult $result, FixtureGenerationJob $job): FixtureSelection
    {
        $selection = $this->selector->select(
            candidates: $job->candidates,
            canSelect: $this->sourceFilter($job),
        );

        $result->scannedFiles = $job->sourceFileCount;
        $result->candidateFiles = $this->candidateFileCount($job->candidates);
        $result->candidateWindows = count($job->candidates);
        $result->candidateFlavours = $selection->flavourCount();
        $result->duplicateCandidates = $selection->duplicateCandidateWindows(count($job->candidates));
        $result->selectedFixtures = $selection->fixtureCount();

        return $selection;
    }

    private function refreshFixtures(
        FixtureGenerationResult $result,
        FixtureGenerationJob $job,
        ?FixtureSelection $selection = null,
        bool $withSourceContext = true,
    ): void {
        $validation = $this->validator->validate(new FixtureValidationOptions(
            fixturesDir: $job->fixturesDir,
            cases: [],
            runner: $job->runner,
            update: true,
            failFast: false,
            refreshPairs: true,
            rewritePathsByCase: $withSourceContext ? $this->rewritePathsByCase($selection, $job->rewriteRoot) : [],
        ));

        foreach ($validation->failures as $failure) {
            $result->fail($failure);
        }

        $result->verifiedPairs = $validation->handled;
        $result->updatedPairs = $validation->updated;
        $result->stalePairs = $validation->stalePairs;
        $result->oldOnly = $validation->oldOnly;
        $result->updatedPairCases = $validation->updatedCases;
        $result->stalePairCases = $validation->staleCases;
        $result->oldOnlyCases = $validation->oldOnlyCases;
    }

    /** @return array<string, string> */
    private function rewritePathsByCase(?FixtureSelection $selection, ?string $rewriteRoot): array
    {
        if (null === $selection || null === $rewriteRoot) {
            return [];
        }

        $paths = [];

        foreach ($selection->fixtures as $fixture) {
            $paths[$this->caseName->fromFixtureSource($fixture)] = $rewriteRoot
                . DIRECTORY_SEPARATOR
                . $fixture->relativePath;
        }

        return $paths;
    }

    /** @param array<string, FixtureWriteResult> $writeResults */
    private function writeDiscoveryReports(
        FixtureGenerationResult $result,
        FixtureGenerationJob $job,
        FixtureSelection $selection,
        array $writeResults,
    ): void {
        if (null === $job->reporter || null === $job->reportsDir) {
            return;
        }

        $job->reporter->write($job->reportsDir, $job->fixturesDir, $result, $selection, $writeResults);
        $result->discoveryReportsWritten = true;
    }

    private function writeRefreshReport(FixtureGenerationResult $result, FixtureGenerationJob $job): void
    {
        if (null === $job->reporter || null === $job->reportsDir) {
            return;
        }

        $job->reporter->writeRefresh($job->reportsDir, $job->fixturesDir, $result);
    }

    /** @param list<FixtureCandidate> $candidates */
    private function candidateFileCount(array $candidates): int
    {
        $files = [];

        foreach ($candidates as $candidate) {
            $files[$candidate->relativePath] = true;
        }

        return count($files);
    }

    /** @return (callable(FixtureSource): bool)|null */
    private function sourceFilter(FixtureGenerationJob $job): ?callable
    {
        if (!$job->write) {
            return null;
        }

        $results = [];
        $verifier = $job->discovery->sourceVerifier();
        $verification = new FixtureSourceVerification(
            fixturesDir: $job->fixturesDir,
            runner: $job->runner,
            rewriteRoot: $job->rewriteRoot,
        );

        return function (FixtureSource $source) use (&$results, $verifier, $verification): bool {
            $results[$source->relativePath] ??= $verifier->canSelect(
                source: $source,
                verification: $verification,
            );

            return $results[$source->relativePath];
        };
    }
}
