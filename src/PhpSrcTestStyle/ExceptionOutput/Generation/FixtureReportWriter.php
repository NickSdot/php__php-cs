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
use function array_values;
use function basename;
use function count;
use function glob;
use function implode;
use function ksort;
use function max;
use function mb_rtrim;
use function mb_str_pad;
use function mb_strlen;
use function preg_replace;
use function sort;
use function str_ends_with;
use function str_repeat;
use function str_replace;
use function str_starts_with;
use function strcmp;
use function usort;

final readonly class FixtureReportWriter implements FixtureReporter
{
    public function __construct(
        private FixtureCaseName $caseName = new FixtureCaseName(),
        private FileSystem $files = new FileSystem(),
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
        $fixtureStates = $this->fixtureStates($fixturesDir, $selection->fixtures, $writeResults);
        $flavourStates = $this->flavourStates($selection, $flavours, $fixtureStates);
        $duplicateGroups = $this->duplicateGroups($flavours);
        $extras = $this->extraFixtureDirs($fixturesDir, $selection->fixtures);
        $legacyDuplicates = $this->legacyDuplicateDirs($extras, $selection->fixtures);

        $this->writeFile($reportsDir . '/stats.md', $this->renderStats($result, $fixtureStates, $flavourStates, $duplicateGroups, $extras, $legacyDuplicates));
        $this->writeFile($reportsDir . '/queue.txt', $this->renderQueue($flavourStates));
        $this->writeFile($reportsDir . '/handled.txt', $this->renderHandled($flavourStates));
        $this->writeFile($reportsDir . '/duplicates.txt', $this->renderDuplicates($duplicateGroups));
        $this->writeFile($reportsDir . '/fixtures.txt', $this->renderFixtures($fixtureStates));
        $this->writeFile($reportsDir . '/flavours.txt', $this->renderFlavourCandidates($flavours));
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
        if (null !== $write?->failure) {
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
        $coverageRows = $this->coverageRows($flavourStates);
        $coverageCounts = $this->coverageCounts($coverageRows);

        $lines = [
            '# Exception output fixture coverage',
            '',
            '## Summary',
            ...$this->table(
                ['Status', 'Flavours'],
                [
                    ['done', $coverageCounts['done']],
                    ['open', $coverageCounts['open']],
                    ['ignored', $coverageCounts['ignored']],
                    ['invalid', $coverageCounts['invalid']],
                ],
            ),
            '## Flavours',
            ...$this->table(
                ['Status', 'Flavour', 'Fixture', 'Detail', 'Fingerprint'],
                $coverageRows,
            ),
            '## Run',
            ...$this->table(
                ['Metric', 'Count'],
                [
                    ['Scanned PHPT files', $result->scannedFiles],
                    ['Files with candidates', $result->candidateFiles],
                    ['Candidate windows', $result->candidateWindows],
                    ['Unique flavours', $result->candidateFlavours],
                    ['Duplicate windows', $result->duplicateCandidates],
                    ['Duplicate flavour groups', count($duplicateGroups)],
                    ['Selected fixture dirs', count($fixtureStates)],
                    ['Avoided extra dirs', max(0, $result->candidateFlavours - count($fixtureStates))],
                    ['Extra dirs outside selection', count($extras)],
                    ['Legacy suffixed dirs', count($legacyDuplicates)],
                    ['Created old fixtures', $result->createdOld],
                    ['Verified fixture files', $result->verifiedPairs],
                    ['Updated new/diff pairs', $result->updatedPairs],
                    ['Stale pairs kept', $result->stalePairs],
                    ['Old-only fixture files', $result->oldOnly],
                    ['Failures', count($result->failures)],
                ],
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
     * @param list<string> $headers
     * @param list<list<int|string>> $rows
     * @return list<string>
     */
    private function table(array $headers, array $rows): array
    {
        $widths = $this->columnWidths($headers, $rows);
        $lines = [
            '',
            $this->tableRow($headers, $widths),
            $this->separatorRow($widths),
        ];

        foreach ($rows as $row) {
            $lines[] = $this->tableRow($row, $widths);
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @param list<string> $headers
     * @param list<list<int|string>> $rows
     * @return list<int>
     */
    private function columnWidths(array $headers, array $rows): array
    {
        $widths = [];

        foreach ($headers as $header) {
            $widths[] = mb_strlen($this->markdownCell($header));
        }

        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                $widths[$index] = max($widths[$index], mb_strlen($this->markdownCell((string) $cell)));
            }
        }

        return array_values($widths);
    }

    /**
     * @param list<int|string> $row
     * @param list<int> $widths
     */
    private function tableRow(array $row, array $widths): string
    {
        $cells = [];

        foreach ($row as $index => $cell) {
            $cells[] = mb_str_pad($this->markdownCell((string) $cell), $widths[$index]);
        }

        return '| ' . implode(' | ', $cells) . ' |';
    }

    /** @param list<int> $widths */
    private function separatorRow(array $widths): string
    {
        $cells = [];

        foreach ($widths as $width) {
            $cells[] = str_repeat('-', $width + 2);
        }

        return '|' . implode('|', $cells) . '|';
    }

    private function markdownCell(string $value): string
    {
        return str_replace('|', '\|', $value);
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
        $lines[] = '  origin: ' . ($this->isManualFixture($candidate) ? 'manual_fixture' : 'php_src');
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
