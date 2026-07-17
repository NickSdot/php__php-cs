<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Generation;

use InternalsCS\Fixture\FixtureCandidate;
use InternalsCS\Fixture\FixtureCaseName;
use InternalsCS\Fixture\FixtureGenerationResult;
use InternalsCS\Fixture\FixturePairFiles;
use InternalsCS\Fixture\FixtureReporter;
use InternalsCS\Fixture\FixtureSelection;
use InternalsCS\Fixture\FixtureSource;
use InternalsCS\Fixture\FixtureWriteResult;
use InternalsCS\Support\FileSystem;

use function array_filter;
use function array_slice;
use function basename;
use function count;
use function glob;
use function implode;
use function ksort;
use function max;
use function mb_rtrim;
use function sort;
use function str_replace;
use function str_starts_with;

final readonly class FixtureReportWriter implements FixtureReporter
{
    public function __construct(
        private FixtureCaseName $caseName = new FixtureCaseName(),
        private FileSystem $files = new FileSystem(),
    ) {}

    /**
     * @param list<FixtureCandidate> $candidates
     * @param array<string, FixtureWriteResult> $writeResults
     */
    public function write(
        string $reportsDir,
        string $fixturesDir,
        FixtureGenerationResult $result,
        array $candidates,
        FixtureSelection $selection,
        array $writeResults,
    ): void {
        $this->files->ensureDirectory($reportsDir, 'report directory');

        $candidates = $this->candidates($candidates);
        $flavours = $this->candidateGroups($selection->flavours);
        $fixtureStates = $this->fixtureStates($fixturesDir, $selection->fixtures, $writeResults);
        $flavourStates = $this->flavourStates($selection, $flavours, $fixtureStates);
        $duplicateGroups = $this->duplicateGroups($flavours);
        $extras = $this->extraFixtureDirs($fixturesDir, $selection->fixtures);
        $legacyDuplicates = $this->legacyDuplicateDirs($extras, $selection->fixtures);

        $this->writeFile($reportsDir . '/stats.txt', $this->renderStats($result, $fixtureStates, $flavourStates, $duplicateGroups, $extras, $legacyDuplicates));
        $this->writeFile($reportsDir . '/queue.txt', $this->renderQueue($flavourStates));
        $this->writeFile($reportsDir . '/handled.txt', $this->renderHandled($flavourStates));
        $this->writeFile($reportsDir . '/duplicates.txt', $this->renderDuplicates($duplicateGroups));
        $this->writeFile($reportsDir . '/legacy_fixtures.txt', $this->renderLegacyFixtures($extras, $legacyDuplicates));
        $this->writeFile($reportsDir . '/fixtures.txt', $this->renderFixtures($fixtureStates));
        $this->writeFile($reportsDir . '/flavours.txt', $this->renderFlavourCandidates($flavours));
        $this->writeFile($reportsDir . '/candidates.txt', $this->renderCandidates($candidates));
        $this->writeFile($reportsDir . '/failures.txt', $this->renderFailures($result, $fixturesDir));
        $this->writeRefresh($reportsDir, $fixturesDir, $result);
    }

    public function writeRefresh(string $reportsDir, string $fixturesDir, FixtureGenerationResult $result): void
    {
        $this->files->ensureDirectory($reportsDir, 'report directory');
        $this->writeFile($reportsDir . '/refresh.txt', $this->renderRefresh($result, $fixturesDir));
    }

    /**
     * @param list<FixtureSource> $fixtures
     * @param array<string, FixtureWriteResult> $writeResults
     * @return list<array{fixture: FixtureSource, dir: string, state: string}>
     */
    private function fixtureStates(string $fixturesDir, array $fixtures, array $writeResults): array
    {
        $states = [];

        foreach ($fixtures as $fixture) {
            $dir = $this->caseName->fromFixtureSource($fixture);
            $fileState = $this->fixtureState($fixturesDir . DIRECTORY_SEPARATOR . $dir);
            $write = $writeResults[$fixture->relativePath] ?? null;

            $states[] = [
                'fixture' => $fixture,
                'dir' => $dir,
                'state' => $this->stateAfterWrite($fileState, $write),
            ];
        }

        return $states;
    }

    private function stateAfterWrite(string $fileState, ?FixtureWriteResult $write): string
    {
        if (null === $write) {
            return $fileState;
        }

        if ($write->verifiedPair) {
            return 'handled';
        }

        if ($write->oldOnly && 'handled' === $fileState) {
            return 'stale_pair';
        }

        if ($write->oldOnly) {
            return 'old_only';
        }

        if (null !== $write->failure) {
            return 'invalid';
        }

        return $fileState;
    }

    private function fixtureState(string $fixtureDir): string
    {
        $files = new FixturePairFiles($fixtureDir);
        $old = $files->hasOld();
        $new = $files->hasNew();
        $diff = $files->hasDiff();

        if ($old && $new && $diff) {
            return 'handled';
        }

        if ($old && !$new && !$diff) {
            return 'old_only';
        }

        if (!$old && !$new && !$diff) {
            return 'missing';
        }

        return 'invalid';
    }

    /**
     * @param array<string, list<Candidate>> $flavours
     * @param list<array{fixture: FixtureSource, dir: string, state: string}> $fixtureStates
     * @return list<array{candidate: Candidate, dir: string, state: string}>
     */
    private function flavourStates(FixtureSelection $selection, array $flavours, array $fixtureStates): array
    {
        $fixtureByFlavour = $selection->fixtureByFlavour();
        $stateBySource = $this->stateBySource($fixtureStates);
        $states = [];

        foreach ($flavours as $flavourKey => $candidates) {
            $candidate = $candidates[0];
            $fixture = $fixtureByFlavour[$flavourKey] ?? null;

            if (null === $fixture) {
                $states[] = [
                    'candidate' => $candidate,
                    'dir' => '',
                    'state' => 'missing',
                ];

                continue;
            }

            $states[] = [
                'candidate' => $candidate,
                'dir' => $this->caseName->fromFixtureSource($fixture),
                'state' => $stateBySource[$fixture->relativePath] ?? 'missing',
            ];
        }

        return $states;
    }

    /**
     * @param list<array{fixture: FixtureSource, dir: string, state: string}> $fixtureStates
     * @return array<string, string>
     */
    private function stateBySource(array $fixtureStates): array
    {
        $states = [];

        foreach ($fixtureStates as $state) {
            $states[$state['fixture']->relativePath] = $state['state'];
        }

        return $states;
    }

    /**
     * @param array<string, list<Candidate>> $flavours
     * @return array<string, list<Candidate>>
     */
    private function duplicateGroups(array $flavours): array
    {
        $groups = array_filter($flavours, static fn(array $group): bool => count($group) > 1);
        ksort($groups);

        return $groups;
    }

    /**
     * @param list<FixtureSource> $fixtures
     * @return list<string>
     */
    private function extraFixtureDirs(string $fixturesDir, array $fixtures): array
    {
        $expected = [];

        foreach ($fixtures as $fixture) {
            $expected[$this->caseName->fromFixtureSource($fixture)] = true;
        }

        $extras = [];
        $fixtureDirs = glob($fixturesDir . '/*', GLOB_ONLYDIR);

        if (false === $fixtureDirs) {
            return [];
        }

        foreach ($fixtureDirs as $dir) {
            if (!new FixturePairFiles($dir)->containsFixtureFiles()) {
                continue;
            }

            $name = basename($dir);

            if (!isset($expected[$name])) {
                $extras[] = $name;
            }
        }

        sort($extras);

        return $extras;
    }

    /**
     * @param list<string> $extras
     * @param list<FixtureSource> $fixtures
     * @return array<string, string>
     */
    private function legacyDuplicateDirs(array $extras, array $fixtures): array
    {
        $sourceDirs = [];

        foreach ($fixtures as $fixture) {
            $sourceDirs[$this->caseName->fromFixtureSource($fixture)] = true;
        }

        $duplicates = [];

        foreach ($extras as $extra) {
            foreach ($sourceDirs as $sourceDir => $_) {
                if (!str_starts_with($extra, $sourceDir . '__')) {
                    continue;
                }

                $duplicates[$extra] = $sourceDir;
                break;
            }
        }

        ksort($duplicates);

        return $duplicates;
    }

    /**
     * @param list<array{fixture: FixtureSource, dir: string, state: string}> $fixtureStates
     * @param list<array{candidate: Candidate, dir: string, state: string}> $flavourStates
     * @param array<string, list<Candidate>> $duplicateGroups
     * @param list<string> $extras
     * @param array<string, string> $legacyDuplicates
     */
    private function renderStats(
        FixtureGenerationResult $result,
        array $fixtureStates,
        array $flavourStates,
        array $duplicateGroups,
        array $extras,
        array $legacyDuplicates,
    ): string {
        $fixtureCounts = $this->stateCounts($fixtureStates);
        $flavourCounts = $this->stateCounts($flavourStates);

        $stats = [
            '# Exception output fixture generation',
            '',
            'Scanned PHPT files: ' . $result->scannedFiles,
            'Candidate files: ' . $result->candidateFiles,
            'Candidate windows: ' . $result->candidateWindows,
            'Candidate flavours: ' . $result->candidateFlavours,
            'Duplicate candidate windows: ' . $result->duplicateCandidates,
            'Duplicate flavour groups: ' . count($duplicateGroups),
            '',
            '# Selected source-file fixtures',
            'Selected fixture source files: ' . count($fixtureStates),
            'Avoided extra per-flavour fixture dirs: ' . max(0, $result->candidateFlavours - count($fixtureStates)),
            'Handled fixture files: ' . ($fixtureCounts['handled'] ?? 0),
            'Queued old-only fixture files: ' . ($fixtureCounts['old_only'] ?? 0),
            'Queued stale fixture files: ' . ($fixtureCounts['stale_pair'] ?? 0),
            'Missing fixture files: ' . ($fixtureCounts['missing'] ?? 0),
            'Invalid fixture files: ' . ($fixtureCounts['invalid'] ?? 0),
            'Remaining fixture queue: ' . (count($fixtureStates) - ($fixtureCounts['handled'] ?? 0)),
            '',
            '# Flavour coverage queue',
            'Flavours covered by handled fixtures: ' . ($flavourCounts['handled'] ?? 0),
            'Flavours covered by old-only fixtures: ' . ($flavourCounts['old_only'] ?? 0),
            'Flavours covered by stale fixtures: ' . ($flavourCounts['stale_pair'] ?? 0),
            'Missing flavour fixtures: ' . ($flavourCounts['missing'] ?? 0),
            'Invalid flavour fixtures: ' . ($flavourCounts['invalid'] ?? 0),
            'Remaining flavour queue: ' . (count($flavourStates) - ($flavourCounts['handled'] ?? 0)),
            '',
            '# Fixture directory shape',
            'Current source-file fixture dirs: ' . count($fixtureStates),
            'Extra fixture dirs outside source-file representatives: ' . count($extras),
            'Legacy suffixed fixture dirs: ' . count($legacyDuplicates),
            '',
            '# Last write run',
            'Created old fixtures: ' . $result->createdOld,
            'Verified fixture files: ' . $result->verifiedPairs,
            'Updated new/diff pairs: ' . $result->updatedPairs,
            'Stale existing new/diff pairs: ' . $result->stalePairs,
            'Old-only fixture files: ' . $result->oldOnly,
            'Failures: ' . count($result->failures),
            '',
        ];

        return implode("\n", $stats);
    }

    /**
     * @param list<array{state: string}> $states
     * @return array<string, int>
     */
    private function stateCounts(array $states): array
    {
        $counts = [];

        foreach ($states as $state) {
            $counts[$state['state']] = ($counts[$state['state']] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /** @param list<array{candidate: Candidate, dir: string, state: string}> $states */
    private function renderQueue(array $states): string
    {
        $lines = ['# Exception output flavour queue', ''];

        foreach ($states as $state) {
            if ('handled' === $state['state']) {
                continue;
            }

            $this->appendFlavourState($lines, $state);
        }

        return implode("\n", $lines);
    }

    /** @param list<array{candidate: Candidate, dir: string, state: string}> $states */
    private function renderHandled(array $states): string
    {
        $lines = ['# Exception output flavours covered by handled fixtures', ''];

        foreach ($states as $state) {
            if ('handled' !== $state['state']) {
                continue;
            }

            $this->appendFlavourState($lines, $state);
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $lines
     * @param array{candidate: Candidate, dir: string, state: string} $state
     */
    private function appendFlavourState(array &$lines, array $state): void
    {
        $candidate = $state['candidate'];
        $lines[] = $candidate->relativePath . ':' . $candidate->line;
        $lines[] = '  state: ' . $state['state'];
        $lines[] = '  fixture: ' . $state['dir'];

        $this->appendCandidateDetails($lines, $candidate);
    }

    /** @param array<string, list<Candidate>> $duplicates */
    private function renderDuplicates(array $duplicates): string
    {
        $lines = ['# Duplicate candidate windows by flavour', ''];

        foreach ($duplicates as $fingerprint => $candidates) {
            $first = $candidates[0];
            $lines[] = $fingerprint;
            $lines[] = '  count: ' . count($candidates);
            $lines[] = '  representative: ' . $first->relativePath . ':' . $first->line;
            $lines[] = '  family: ' . $first->classification->family->value;
            $lines[] = '  safety: ' . $first->classification->safety->value;
            $lines[] = '  first statement: ' . $first->statement;
            $lines[] = '  duplicates:';

            foreach (array_slice($candidates, 1, 20) as $candidate) {
                $lines[] = '    - ' . $candidate->relativePath . ':' . $candidate->line;
            }

            if (count($candidates) > 21) {
                $lines[] = '    - +' . (count($candidates) - 21) . ' more';
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $extras
     * @param array<string, string> $legacyDuplicates
     */
    private function renderLegacyFixtures(array $extras, array $legacyDuplicates): string
    {
        $lines = ['# Fixture dirs outside the current source-file representative set', ''];
        $lines[] = 'These are not deleted automatically.';
        $lines[] = '';
        $lines[] = '## Legacy suffixed dirs';
        $lines[] = '';

        foreach ($legacyDuplicates as $legacy => $representative) {
            $lines[] = $legacy;
            $lines[] = '  current source-file fixture dir: ' . $representative;
            $lines[] = '';
        }

        $lines[] = '## Other extra dirs';
        $lines[] = '';

        foreach ($extras as $extra) {
            if (isset($legacyDuplicates[$extra])) {
                continue;
            }

            $lines[] = '- ' . $extra;
        }

        return implode("\n", $lines);
    }

    /** @param list<array{fixture: FixtureSource, dir: string, state: string}> $states */
    private function renderFixtures(array $states): string
    {
        $lines = ['# Selected source-file fixtures', ''];

        foreach ($states as $state) {
            $fixture = $state['fixture'];
            $firstCandidate = $this->candidate($fixture->firstCandidate());
            $lines[] = $fixture->relativePath;
            $lines[] = '  state: ' . $state['state'];
            $lines[] = '  fixture: ' . $state['dir'];
            $lines[] = '  first line: ' . $firstCandidate->line;
            $lines[] = '  flavours covered: ' . count($fixture->flavourKeys());

            foreach ($fixture->candidates as $candidate) {
                $candidate = $this->candidate($candidate);
                $lines[] = '    - line ' . $candidate->line . ': ' . $candidate->classification->family->value . ' ' . $candidate->key;
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /** @param array<string, list<Candidate>> $flavours */
    private function renderFlavourCandidates(array $flavours): string
    {
        $candidates = [];

        foreach ($flavours as $group) {
            $candidates[] = $group[0];
        }

        return $this->renderCandidates($candidates, 'Exception output flavours');
    }

    /** @param list<Candidate> $candidates */
    private function renderCandidates(array $candidates, string $title = 'Exception output candidates'): string
    {
        $lines = ['# ' . $title, ''];

        foreach ($candidates as $candidate) {
            $lines[] = $candidate->relativePath . ':' . $candidate->line;

            $this->appendCandidateDetails($lines, $candidate);
        }

        return implode("\n", $lines);
    }

    /** @param list<string> $lines */
    private function appendCandidateDetails(array &$lines, Candidate $candidate): void
    {
        $lines[] = '  fingerprint: ' . $candidate->key;
        $lines[] = '  family: ' . $candidate->classification->family->value;
        $lines[] = '  safety: ' . $candidate->classification->safety->value;
        $lines[] = '  expected: ' . $candidate->expectedSection;
        $lines[] = '  reason: ' . $candidate->classification->reason;
        $lines[] = '  parts: ' . $candidate->classification->partsSummary;
        $lines[] = '  ' . $candidate->statement;
        $lines[] = '';
        $lines[] = $candidate->context;
        $lines[] = '';
    }

    private function renderFailures(FixtureGenerationResult $result, string $fixturesDir): string
    {
        $lines = ['# Fixture generation failures', ''];

        foreach ($result->failures as $failure) {
            $lines[] = '- ' . $this->normalizeFailure($failure, $fixturesDir);
        }

        return implode("\n", $lines) . "\n";
    }

    private function renderRefresh(FixtureGenerationResult $result, string $fixturesDir): string
    {
        /** @var list<string> $lines */
        $lines = [
            '# Fixture refresh',
            '',
            'Refresh-only run: ' . ($result->refreshOnly ? 'yes' : 'no'),
            'Updated new/diff pairs: ' . $result->updatedPairs,
            'Stale existing new/diff pairs kept: ' . $result->stalePairs,
            'Old-only fixture files: ' . $result->oldOnly,
            'Failures: ' . count($result->failures),
            '',
            '## Updated pairs',
            '',
        ];

        $this->appendList($lines, $result->updatedPairCases);

        $lines[] = '';
        $lines[] = '## Stale existing pairs kept';
        $lines[] = '';
        $lines[] = 'These existing new.phpt/ran.diff pairs were kept, but the current fixer did not reproduce a change from old.phpt.';
        $lines[] = '';

        $this->appendList($lines, $result->stalePairCases);

        $lines[] = '';
        $lines[] = '## Old-only fixtures';
        $lines[] = '';

        $this->appendList($lines, $result->oldOnlyCases);

        $lines[] = '';
        $lines[] = '## Failures';
        $lines[] = '';

        foreach ($result->failures as $failure) {
            $lines[] = '- ' . $this->normalizeFailure($failure, $fixturesDir);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<string> $lines
     * @param list<string> $items
     */
    private function appendList(array &$lines, array $items): void
    {
        if ([] === $items) {
            $lines[] = '- none';
            return;
        }

        foreach ($items as $item) {
            $lines[] = '- ' . $item;
        }
    }

    private function normalizeFailure(string $failure, string $fixturesDir): string
    {
        return str_replace(mb_rtrim($fixturesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, '', $failure);
    }

    private function writeFile(string $path, string $contents): void
    {
        $this->files->write($path, $contents, 'report');
    }

    private function candidate(FixtureCandidate $candidate): Candidate
    {
        if (!$candidate instanceof Candidate) {
            throw new \LogicException('Exception output reports can only render exception output candidates');
        }

        return $candidate;
    }

    /**
     * @param list<FixtureCandidate> $candidates
     * @return list<Candidate>
     */
    private function candidates(array $candidates): array
    {
        $typed = [];

        foreach ($candidates as $candidate) {
            $typed[] = $this->candidate($candidate);
        }

        return $typed;
    }

    /**
     * @param array<string, list<FixtureCandidate>> $groups
     * @return array<string, list<Candidate>>
     */
    private function candidateGroups(array $groups): array
    {
        $typed = [];

        foreach ($groups as $key => $candidates) {
            $typed[$key] = $this->candidates($candidates);
        }

        return $typed;
    }
}
