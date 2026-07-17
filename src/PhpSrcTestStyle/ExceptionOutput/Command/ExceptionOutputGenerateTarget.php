<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Command;

use InternalsCS\Command\GenerateOptions;
use InternalsCS\Command\GenerateTarget;
use InternalsCS\Console\ConsoleIo;
use InternalsCS\Fixture\FixtureGenerationResult;
use InternalsCS\Fixture\FixtureGenerator;
use InternalsCS\Fixture\FixtureRewriteRunner;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Generation\FixtureReportWriter;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Generation\Scanner;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Validation\PhptFixtureRewriteRunner;

use function count;
use function function_exists;

final readonly class ExceptionOutputGenerateTarget implements GenerateTarget
{
    public function __construct(
        private FixtureGenerator $generator = new FixtureGenerator(
            scanner: new Scanner(),
            reports: new FixtureReportWriter(),
        ),
    ) {}

    public function name(): string
    {
        return 'exception-output';
    }

    public function description(): string
    {
        return 'Generate exception-output PHPT fixtures and reports';
    }

    public function sourceExtensions(): array
    {
        return ['phpt'];
    }

    public function defaultFixturesDir(string $toolRoot): string
    {
        return $toolRoot . '/tests/Fixtures/exception_output_styles';
    }

    public function defaultReportsDir(string $toolRoot): string
    {
        return $toolRoot . '/reports/exception_output_styles';
    }

    public function checkRuntime(ConsoleIo $io): bool
    {
        if (function_exists('token_get_all')) {
            return true;
        }

        $io->err("generate exception-output requires the tokenizer extension\n");

        return false;
    }

    public function requiresPhpTestRuntime(): bool
    {
        return true;
    }

    public function generator(): FixtureGenerator
    {
        return $this->generator;
    }

    public function rewriteRunner(GenerateOptions $options): FixtureRewriteRunner
    {
        return new PhptFixtureRewriteRunner($options->phpTestRuntimeRoot->path);
    }

    public function printResult(FixtureGenerationResult $result, ConsoleIo $io): int
    {
        foreach ($result->warnings as $warning) {
            $io->err('Warning: ' . $warning . "\n");
        }

        $io->out('Scanned ' . $result->scannedFiles . " PHPT files\n");
        $io->out('Found ' . $result->candidateFiles . " exception output candidate files\n");
        $io->out('Found ' . $result->candidateWindows . " exception output candidate windows\n");
        $io->out('Grouped ' . $result->candidateFlavours . " exception output flavours\n");
        $io->out('Skipped ' . $result->duplicateCandidates . " duplicate candidate windows\n");
        $io->out('Selected ' . $result->selectedFixtures . " fixture source files\n");

        if ($result->dryRun) {
            $io->out("Dry run only; pass --write to add/update fixtures and reports\n");
        } else {
            $this->printWriteSummary($result, $io);
        }

        if (!$result->failed()) {
            return 0;
        }

        $io->err('Generation completed with ' . count($result->failures) . " failure(s):\n");

        foreach ($result->failures as $failure) {
            $io->err(' - ' . $failure . "\n");
        }

        return 1;
    }

    private function printWriteSummary(FixtureGenerationResult $result, ConsoleIo $io): void
    {
        if ($result->refreshOnly) {
            $summary = $result->discoveryReportsWritten
                ? "Refreshed existing fixtures and recomputed source discovery reports\n"
                : "Refreshed existing fixtures only; source discovery reports were left unchanged\n";

            $io->out($summary);
        }

        $io->out('Created ' . $result->createdOld . " old.phpt fixtures\n");
        $io->out('Verified ' . $result->verifiedPairs . " existing fixture pairs\n");
        $io->out('Updated ' . $result->updatedPairs . " new.phpt/ran.diff pairs\n");
        $io->out('Kept ' . $result->stalePairs . " stale new.phpt/ran.diff pairs\n");
        $io->out('Kept ' . $result->oldOnly . " old-only fixtures\n");
    }
}
