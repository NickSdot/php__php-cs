<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use InternalsCS\SourceFile;
use InternalsCS\SourceFinder;
use InternalsCS\Support\GitStatus;

use function array_any;
use function array_fill_keys;
use function array_key_exists;
use function array_keys;
use function basename;
use function count;
use function dirname;
use function glob;
use function is_dir;
use function str_ends_with;
use function str_starts_with;

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
            : $this->candidatesByFixer($discoveries, $sourceFiles);
        $fixtureCandidatesByFixer = $sourceDirty
            ? $this->emptyCandidateMap($discoveries)
            : $this->fixtureCandidatesByFixer($discoveries, $options->fixturesRoot, $candidatesByFixer);
        $sourceFileCount = count($sourceFiles);
        $runs = [];

        foreach ($discoveries as $discovery) {
            $job = new FixtureGenerationJob(
                discovery: $discovery,
                sourceDirty: $sourceDirty,
                options: $options,
                sourceFileCount: $sourceFileCount,
                candidates: $candidatesByFixer[$discovery->fixerName()],
                fixtureCandidates: $fixtureCandidatesByFixer[$discovery->fixerName()],
            );

            $run = new FixtureGenerationRun(
                fixer: $job->fixer,
                fixturesDir: $job->fixturesDir,
                reportsDir: $job->reportsDir,
                result: $this->generateFixtures($job),
            );
            $runs[] = $run;

            if ($options->write && $run->result->failed()) {
                break;
            }
        }

        $reportPath = null;

        if ($options->write && !array_any($runs, static fn(FixtureGenerationRun $run): bool => $run->result->failed())) {
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
    private function candidatesByFixer(array $discoveries, array $sourceFiles): array
    {
        $candidates = $this->emptyCandidateMap($discoveries);

        foreach ($sourceFiles as $sourceFile) {
            foreach ($discoveries as $discovery) {
                foreach ($discovery->candidates($sourceFile) as $candidate) {
                    $fixer = $discovery->fixerName();
                    $candidates[$fixer][] = $candidate;
                }
            }
        }

        return $candidates;
    }

    /**
     * @param list<FixtureDiscovery> $discoveries
     * @param array<string, list<FixtureCandidate>> $sourceCandidates
     * @return array<string, list<FixtureCandidate>>
     */
    private function fixtureCandidatesByFixer(array $discoveries, string $fixturesRoot, array $sourceCandidates): array
    {
        $candidates = $this->emptyCandidateMap($discoveries);

        foreach ($discoveries as $discovery) {
            $fixer = $discovery->fixerName();
            $sourceCases = $this->fixtureCases($sourceCandidates[$fixer]);

            foreach ($this->fixtureSources($discovery, $fixturesRoot) as $fixtureSource) {
                foreach ($discovery->candidates($fixtureSource) as $candidate) {
                    if (!isset($sourceCases[$candidate->fixtureCaseKey])) {
                        continue;
                    }

                    $candidates[$fixer][] = $candidate;
                }
            }
        }

        return $candidates;
    }

    /**
     * @param list<FixtureCandidate> $candidates
     * @return array<string, true>
     */
    private function fixtureCases(array $candidates): array
    {
        $cases = [];

        foreach ($candidates as $candidate) {
            $cases[$candidate->fixtureCaseKey] = true;
        }

        return $cases;
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
    private function fixtureSources(FixtureDiscovery $discovery, string $fixturesRoot): array
    {
        $fixturesDir = $discovery->fixturesDir($fixturesRoot);

        if (!is_dir($fixturesDir)) {
            return [];
        }

        $files = glob($fixturesDir . '/*/old.phpt');

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

        $oldFixtureContentsByCase = [];
        $sourceFilter = $this->sourceFilter($job, $oldFixtureContentsByCase);
        $selection = $this->select($result, $job, $sourceFilter);

        if (!$job->write || $result->failed()) {
            return $result;
        }

        $writeResults = [];

        foreach ($selection->fixtures as $fixture) {
            $case = $this->caseName->fromFixtureSource($fixture);
            $writeResult = $this->writer->write(
                source: $fixture,
                fixturesDir: $job->fixturesDir,
                oldContents: $oldFixtureContentsByCase[$case] ?? null,
            );
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

        if (null !== $sourceFilter) {
            $this->removeRejectedFixtures($result, $job, $sourceFilter, $selection);
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

        $oldFixtureContentsByCase = [];
        $selection = $this->select($result, $job, $this->sourceFilter($job, $oldFixtureContentsByCase));

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

    /** @param (callable(FixtureSource): bool)|null $sourceFilter */
    private function select(FixtureGenerationResult $result, FixtureGenerationJob $job, ?callable $sourceFilter): FixtureSelection
    {
        $selection = $this->selector->select(
            candidates: $job->candidates,
            canSelect: $sourceFilter,
            splitSourceCandidatesByFixtureCase: !$job->refreshOnly && null !== $job->sourceReducer,
            fixtureCandidates: $job->fixtureCandidates,
        );

        $result->scannedFiles = $job->sourceFileCount;
        $result->candidateFiles = $this->candidateFileCount($job->candidates);
        $result->candidateWindows = count($job->candidates);
        $result->candidateFlavours = $selection->flavourCount();
        $result->duplicateCandidates = $selection->duplicateCandidateWindows(count($job->candidates));
        $result->selectedFixtures = $selection->fixtureCount();

        $uncoveredFlavours = $selection->uncoveredFlavours();

        if ($job->write && !$job->refreshOnly && [] !== $uncoveredFlavours) {
            foreach ($uncoveredFlavours as $flavour => $candidates) {
                $result->fail(
                    'no verified fixture source for flavour '
                    . $flavour
                    . ' (first candidate: '
                    . $candidates[0]->relativePath
                    . ')',
                );
            }

            return $selection;
        }

        return $selection;
    }

    /** @param callable(FixtureSource): bool $canSelect */
    private function removeRejectedFixtures(
        FixtureGenerationResult $result,
        FixtureGenerationJob $job,
        callable $canSelect,
        FixtureSelection $selection,
    ): void {
        $selectedCases = array_fill_keys($this->selectedFixtureCases($selection), true);

        foreach ($this->existingGeneratedFixtureSources($job) as $source) {
            $case = $this->fixtureSourceCase($source, $job->fixturesDir);

            if (isset($selectedCases[$case])) {
                continue;
            }

            if (null !== $job->sourceReducer) {
                if (!$this->writer->remove($source, $job->fixturesDir)) {
                    continue;
                }

                $result->removedFixtures++;
                $result->removedFixtureCases[] = $case;
                continue;
            }

            if ($canSelect($source)) {
                continue;
            }

            if (!$this->writer->remove($source, $job->fixturesDir)) {
                continue;
            }

            $result->removedFixtures++;
            $result->removedFixtureCases[] = $case;
        }
    }

    private function fixtureSourceCase(FixtureSource $source, string $fixturesDir): string
    {
        if (
            str_starts_with($source->sourcePath, $fixturesDir . DIRECTORY_SEPARATOR)
            && str_ends_with($source->sourcePath, DIRECTORY_SEPARATOR . 'old.phpt')
        ) {
            return basename(dirname($source->sourcePath));
        }

        return $this->caseName->fromFixtureSource($source);
    }

    /** @return list<string> */
    private function selectedFixtureCases(FixtureSelection $selection): array
    {
        $cases = [];

        foreach ($selection->fixtures as $fixture) {
            $cases[] = $this->caseName->fromFixtureSource($fixture);
        }

        return $cases;
    }

    /** @return list<FixtureSource> */
    private function existingGeneratedFixtureSources(FixtureGenerationJob $job): array
    {
        $candidatesBySource = [];
        $files = glob($job->fixturesDir . '/*/old.phpt');

        if (false === $files) {
            return [];
        }

        foreach ($files as $file) {
            $source = new SourceFile($file, $job->fixturesDir);

            foreach ($job->discovery->candidates($source) as $candidate) {
                $candidatesBySource[$candidate->relativePath][] = $candidate;
            }
        }

        foreach ($job->candidates as $candidate) {
            $fixtureDir = $job->fixturesDir
                . DIRECTORY_SEPARATOR
                . $this->caseName->fromCandidate($candidate);

            if (!new FixturePairFiles($fixtureDir)->containsFixtureFiles()) {
                continue;
            }

            $candidatesBySource[$candidate->relativePath][] = $candidate;
        }

        $sources = [];

        foreach ($candidatesBySource as $candidates) {
            $sources[] = new FixtureSource($candidates);
        }

        return $sources;
    }

    private function isExistingFixtureSource(FixtureSource $source, FixtureGenerationJob $job): bool
    {
        return str_starts_with($source->sourcePath, $job->fixturesDir . DIRECTORY_SEPARATOR)
            && str_ends_with($source->relativePath, '/old.phpt');
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

    /**
     * @param array<string, string|null> $oldFixtureContentsByCase
     * @return (callable(FixtureSource): bool)|null
     */
    private function sourceFilter(FixtureGenerationJob $job, array &$oldFixtureContentsByCase): ?callable
    {
        if (!$job->write) {
            return null;
        }

        $verifiedSources = [];

        return function (FixtureSource $source) use (&$oldFixtureContentsByCase, &$verifiedSources, $job): bool {
            if ($this->shouldReduceSource($source, $job)) {
                return $this->reduceFixtureSource($source, $job, $oldFixtureContentsByCase);
            }

            return $this->verifyFixtureSource($source, $job, $verifiedSources);
        };
    }

    private function shouldReduceSource(FixtureSource $source, FixtureGenerationJob $job): bool
    {
        return !$job->refreshOnly
            && null !== $job->sourceReducer
            && !$this->isExistingFixtureSource($source, $job);
    }

    /** @param array<string, string|null> $oldFixtureContentsByCase */
    private function reduceFixtureSource(FixtureSource $source, FixtureGenerationJob $job, array &$oldFixtureContentsByCase): bool
    {
        if (null === $job->sourceReducer) {
            return false;
        }

        $case = $this->caseName->fromFixtureSource($source);

        if (!array_key_exists($case, $oldFixtureContentsByCase)) {
            $oldFixtureContentsByCase[$case] = $job->sourceReducer->reduce(
                source: $source,
                runner: $job->runner,
            );
        }

        return null !== $oldFixtureContentsByCase[$case];
    }

    /** @param array<string, bool> $verifiedSources */
    private function verifyFixtureSource(FixtureSource $source, FixtureGenerationJob $job, array &$verifiedSources): bool
    {
        $verifiedSources[$source->relativePath] ??= $job->discovery->sourceVerifier()->canSelect(
            source: $source,
            verification: new FixtureSourceVerification(
                fixturesDir: $job->fixturesDir,
                runner: $job->runner,
            ),
        );

        return $verifiedSources[$source->relativePath];
    }
}
