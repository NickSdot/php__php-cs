<?php

declare(strict_types=1);

namespace InternalsCS\Command;

use InternalsCS\Console\ConsoleIo;
use InternalsCS\Fixture\FixtureGenerationRun;

use function count;

final readonly class GenerateRunPrinter
{
    public function print(FixtureGenerationRun $run, ConsoleIo $io): void
    {
        $result = $run->result;

        foreach ($result->warnings as $warning) {
            $io->err('Warning: ' . $run->fixer . ': ' . $warning . "\n");
        }

        $io->out($run->fixer . " fixtures\n");
        $io->out('Used ' . $result->scannedFiles . " input source files\n");
        $io->out('Found ' . $result->candidateFiles . " candidate files\n");
        $io->out('Grouped ' . $result->candidateFlavours . " flavours\n");
        $io->out('Selected ' . $result->selectedFixtures . " fixture source files\n");

        if ($result->dryRun) {
            $io->out("Dry run only; pass --write to add/update fixtures and reports\n");
            return;
        }

        if ($result->refreshOnly) {
            $io->out($this->refreshSummary($result->discoveryReportsWritten));
        }

        $io->out('Created ' . $result->createdOld . " old.phpt fixtures\n");
        $io->out('Verified ' . $result->verifiedPairs . " existing fixture pairs\n");
        $io->out('Updated ' . $result->updatedPairs . " new.phpt/ran.diff pairs\n");
        $io->out('Kept ' . $result->stalePairs . " stale new.phpt/ran.diff pairs\n");
        $io->out('Kept ' . $result->oldOnly . " old-only fixtures\n");

        if (!$result->failed()) {
            return;
        }

        $io->err('Generation completed with ' . count($result->failures) . ' ' . $run->fixer . " failure(s):\n");

        foreach ($result->failures as $failure) {
            $io->err(' - ' . $failure . "\n");
        }
    }

    private function refreshSummary(bool $reportsWritten): string
    {
        if ($reportsWritten) {
            return "Refreshed existing fixtures and recomputed source discovery reports\n";
        }

        return "Refreshed existing fixtures only; source discovery reports were left unchanged\n";
    }
}
