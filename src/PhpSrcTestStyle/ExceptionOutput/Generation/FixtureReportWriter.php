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
use InternalsCS\Support\MarkdownTable;

use function array_fill_keys;
use function array_filter;
use function array_map;
use function array_slice;
use function count;
use function implode;
use function ksort;
use function mb_rtrim;
use function preg_replace;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strcmp;
use function usort;

final readonly class FixtureReportWriter implements FixtureReporter
{
    public function __construct(
        private FixtureCaseName $caseName = new FixtureCaseName(),
        private FileSystem $files = new FileSystem(),
        private MarkdownTable $table = new MarkdownTable(),
    ) {}

    /** @param array<string, FixtureWriteResult> $writeResults */
    public function write(
        string $reportsDir,
        string $fixturesDir,
        FixtureGenerationResult $result,
        FixtureSelection $selection,
        array $writeResults,
    ): void {
        $this->files->ensureDirectory($reportsDir, 'report directory');

        $flavours = $this->candidateGroups($selection->flavours);
        $fixtureStates = $this->fixtureStates($fixturesDir, $selection->fixtures, $writeResults, $result);
        $flavourStates = $this->flavourStates($selection, $flavours, $fixtureStates);
        $duplicateGroups = $this->duplicateGroups($flavours);
        $selectedFixtures = $this->selectedFixturesByFlavour($selection);

        $this->writeFile($reportsDir . '/stats.md', $this->renderStats($result, $flavourStates, $duplicateGroups));
        $this->writeFile($reportsDir . '/duplicates.txt', $this->renderDuplicates($duplicateGroups, $selectedFixtures));
        $this->writeFile($reportsDir . '/fixtures.txt', $this->renderFixtures($fixtureStates));
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
    private function fixtureStates(
        string $fixturesDir,
        array $fixtures,
        array $writeResults,
        FixtureGenerationResult $result,
    ): array {
        $staleCases = array_fill_keys($result->stalePairCases, true);
        $oldOnlyCases = array_fill_keys($result->oldOnlyCases, true);
        $states = [];

        foreach ($fixtures as $fixture) {
            $dir = $this->caseName->fromFixtureSource($fixture);
            $fileState = $this->fixtureState($fixturesDir . DIRECTORY_SEPARATOR . $dir);
            $write = $writeResults[$fixture->relativePath] ?? null;

            $states[] = [
                'fixture' => $fixture,
                'dir' => $dir,
                'state' => $this->stateAfterRun($dir, $fileState, $write, $staleCases, $oldOnlyCases),
            ];
        }

        return $states;
    }

    /**
     * @param array<string, true> $staleCases
     * @param array<string, true> $oldOnlyCases
     */
    private function stateAfterRun(
        string $dir,
        string $fileState,
        ?FixtureWriteResult $write,
        array $staleCases,
        array $oldOnlyCases,
    ): string {
        if (null !== $write?->failure) {
            return 'invalid';
        }

        if (isset($staleCases[$dir])) {
            return 'stale_pair';
        }

        if (isset($oldOnlyCases[$dir])) {
            return 'old_only';
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
                    'state' => 'unselected',
                ];

                continue;
            }

            $states[] = [
                'candidate' => $this->selectedCandidate($fixture, $flavourKey) ?? $candidate,
                'dir' => $this->caseName->fromFixtureSource($fixture),
                'state' => $stateBySource[$fixture->relativePath] ?? 'missing',
            ];
        }

        return $states;
    }

    private function selectedCandidate(FixtureSource $fixture, string $flavourKey): ?Candidate
    {
        foreach ($fixture->candidates as $candidate) {
            if ($candidate->fixtureKey() === $flavourKey) {
                return $this->candidate($candidate);
            }
        }

        return null;
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

    /** @return array<string, string> */
    private function selectedFixturesByFlavour(FixtureSelection $selection): array
    {
        return array_map($this->caseName->fromFixtureSource(...), $selection->fixtureByFlavour());
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
     * @param list<array{candidate: Candidate, dir: string, state: string}> $flavourStates
     * @param array<string, list<Candidate>> $duplicateGroups
     */
    private function renderStats(
        FixtureGenerationResult $result,
        array $flavourStates,
        array $duplicateGroups,
    ): string {
        $coverageRows = $this->coverageRows($flavourStates);
        $coverageCounts = $this->coverageCounts($coverageRows);

        $lines = [
            '# Exception output fixture coverage',
            '',
            '## Run',
            ...$this->table->render(
                ['Metric', 'Count'],
                [
                    ['Scanned source files', $result->scannedFiles],
                    ['Source files with candidates', $result->candidateFiles],
                    ['Candidate windows', $result->candidateWindows],
                    ['Unique flavours', $result->candidateFlavours],
                    ['Duplicate candidate windows', $result->duplicateCandidates],
                    ['Duplicate flavour groups', count($duplicateGroups)],
                    ['Selected fixture files', $result->selectedFixtures],
                    ['Created old fixtures', $result->createdOld],
                    ['Verified fixture pairs', $result->verifiedPairs],
                    ['Updated fixture pairs', $result->updatedPairs],
                    ['Stale fixture pairs', $result->stalePairs],
                    ['Failures', count($result->failures)],
                ],
            ),
            '## Summary',
            ...$this->table->render(
                ['Status', 'Flavours'],
                [
                    ['done', $coverageCounts['done']],
                    ['open', $coverageCounts['open']],
                    ['ignored', $coverageCounts['ignored']],
                    ['invalid', $coverageCounts['invalid']],
                ],
            ),
            '## Flavours',
            ...$this->table->render(
                ['Status', 'Flavour', 'Fixture', 'Detail', 'Fingerprint'],
                $coverageRows,
            ),
        ];

        return implode("\n", $lines);
    }

    /**
     * @param list<array{0: string, 1: string, 2: string, 3: string, 4: string}> $rows
     * @return array{done: int, open: int, ignored: int, invalid: int}
     */
    private function coverageCounts(array $rows): array
    {
        $done = 0;
        $open = 0;
        $ignored = 0;
        $invalid = 0;

        foreach ($rows as $row) {
            match ($row[0]) {
                'done' => $done++,
                'open' => $open++,
                'ignored' => $ignored++,
                default => $invalid++,
            };
        }

        return [
            'done' => $done,
            'open' => $open,
            'ignored' => $ignored,
            'invalid' => $invalid,
        ];
    }

    /**
     * @param list<array{candidate: Candidate, dir: string, state: string}> $states
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    private function coverageRows(array $states): array
    {
        $rows = [];

        foreach ($states as $state) {
            $candidate = $state['candidate'];
            $status = $this->coverageStatus($state['state']);
            $fixture = '' === $state['dir'] ? '-' : $state['dir'];

            $rows[] = [
                $status,
                $this->oneLine($candidate->statement),
                $fixture,
                $this->coverageDetail($state),
                $candidate->key,
            ];
        }

        usort($rows, fn(array $a, array $b): int => $this->compareCoverageRows($a, $b));

        return $rows;
    }

    /** @param array{candidate: Candidate, dir: string, state: string} $state */
    private function coverageDetail(array $state): string
    {
        $candidate = $state['candidate'];
        $detail = match ($state['state']) {
            'handled' => 'verified',
            'old_only' => 'old_only_fixture',
            'stale_pair' => 'stale_pair_kept',
            'missing' => 'selected_fixture_missing_files',
            'unselected' => 'no_selected_runnable_dir',
            default => 'invalid_fixture_shape',
        };

        if ($this->isManualFixture($candidate)) {
            $detail = 'manual_' . $detail;
        }

        return $detail . '; ' . $candidate->relativePath . ':' . $candidate->line;
    }

    private function coverageStatus(string $state): string
    {
        return match ($state) {
            'handled' => 'done',
            'old_only', 'stale_pair', 'unselected' => 'open',
            'ignored' => 'ignored',
            default => 'invalid',
        };
    }

    /**
     * @param array{0: string, 1: string, 2: string, 3: string, 4: string} $a
     * @param array{0: string, 1: string, 2: string, 3: string, 4: string} $b
     */
    private function compareCoverageRows(array $a, array $b): int
    {
        $status = $this->statusPriority($a[0]) <=> $this->statusPriority($b[0]);

        if (0 !== $status) {
            return $status;
        }

        $detail = strcmp($a[3], $b[3]);

        if (0 !== $detail) {
            return $detail;
        }

        return strcmp($a[1], $b[1]);
    }

    private function statusPriority(string $status): int
    {
        return match ($status) {
            'open' => 0,
            'invalid' => 1,
            'ignored' => 2,
            default => 3,
        };
    }

    private function oneLine(string $value): string
    {
        return (string) preg_replace('/\s+/', ' ', $value);
    }

    /**
     * @param array<string, list<Candidate>> $duplicates
     * @param array<string, string> $selectedFixtures
     */
    private function renderDuplicates(array $duplicates, array $selectedFixtures): string
    {
        return implode("\n", [
            '# Duplicate candidate windows by flavour',
            ...$this->table->render(
                ['Count', 'Fixture', 'First', 'Duplicates', 'Detail', 'Fingerprint'],
                $this->duplicateRows($duplicates, $selectedFixtures),
            ),
        ]);
    }

    /**
     * @param array<string, list<Candidate>> $duplicates
     * @param array<string, string> $selectedFixtures
     * @return list<array{0: int, 1: string, 2: string, 3: string, 4: string, 5: string}>
     */
    private function duplicateRows(array $duplicates, array $selectedFixtures): array
    {
        $rows = [];

        foreach ($duplicates as $fingerprint => $candidates) {
            if ([] === $candidates) {
                continue;
            }

            $first = $candidates[0];
            $rows[] = [
                count($candidates),
                $selectedFixtures[$fingerprint] ?? 'none',
                $this->candidateLocation($first),
                $this->duplicateLocations($candidates),
                $first->classification->family->value . '/' . $first->classification->safety->value,
                $fingerprint,
            ];
        }

        return $rows;
    }

    /** @param list<Candidate> $candidates */
    private function duplicateLocations(array $candidates): string
    {
        $locations = [];

        foreach (array_slice($candidates, 1, 3) as $candidate) {
            $locations[] = $this->candidateLocation($candidate);
        }

        $remaining = count($candidates) - 4;

        if ($remaining > 0) {
            $locations[] = '+' . $remaining . ' more';
        }

        return implode(', ', $locations);
    }

    private function candidateLocation(Candidate $candidate): string
    {
        return $candidate->relativePath . ':' . $candidate->line;
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

    private function isManualFixture(Candidate $candidate): bool
    {
        return str_starts_with($candidate->relativePath, 'manual_')
            && str_ends_with($candidate->relativePath, '/old.phpt');
    }

    private function renderFailures(FixtureGenerationResult $result, string $fixturesDir): string
    {
        $lines = ['# Fixture generation failures', ''];

        $this->appendFailures($lines, $result, $fixturesDir);

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

        $this->appendFailures($lines, $result, $fixturesDir);

        return implode("\n", $lines) . "\n";
    }

    /** @param list<string> $lines */
    private function appendFailures(array &$lines, FixtureGenerationResult $result, string $fixturesDir): void
    {
        if ([] === $result->failures) {
            $lines[] = '- none';
            return;
        }

        foreach ($result->failures as $failure) {
            $lines[] = '- ' . $this->normalizeFailure($failure, $fixturesDir);
        }
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
        return array_map($this->candidate(...), $candidates);
    }

    /**
     * @param array<string, list<FixtureCandidate>> $groups
     * @return array<string, list<Candidate>>
     */
    private function candidateGroups(array $groups): array
    {
        return array_map($this->candidates(...), $groups);
    }
}
