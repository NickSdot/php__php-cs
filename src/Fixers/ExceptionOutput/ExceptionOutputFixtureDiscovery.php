<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput;

use InternalsCS\Console\ConsoleIo;
use InternalsCS\Fixers\ExceptionOutput\Generation\CandidateCollector;
use InternalsCS\Fixers\ExceptionOutput\Generation\FixtureReportWriter;
use InternalsCS\Fixers\ExceptionOutput\Generation\SourceVerifier;
use InternalsCS\Fixture\FixtureDiscovery;
use InternalsCS\Fixture\FixtureReporter;
use InternalsCS\Fixture\FixtureRewriteRunner;
use InternalsCS\Fixture\FixtureSourceVerifier;
use InternalsCS\PhpSrc\PhpSrcRoot;
use InternalsCS\PhpSrcTestStyle\PhptFixtureRewriteRunner;
use InternalsCS\SourceFile;

use function function_exists;

final readonly class ExceptionOutputFixtureDiscovery implements FixtureDiscovery
{
    public function __construct(
        private CandidateCollector $candidates = new CandidateCollector(),
        private FixtureReporter $reporter = new FixtureReportWriter(),
        private FixtureSourceVerifier $sourceVerifier = new SourceVerifier(),
    ) {}

    public function fixerName(): string
    {
        return 'exception-output';
    }

    public function sourceExtensions(): array
    {
        return ['phpt'];
    }

    public function fixturesDir(string $fixturesRoot): string
    {
        return $fixturesRoot . '/exception_output_styles';
    }

    public function reportsDir(string $reportsRoot): string
    {
        return $reportsRoot . '/exception_output_styles';
    }

    public function candidates(SourceFile $source): array
    {
        return $this->candidates->collect($source);
    }

    public function reporter(): FixtureReporter
    {
        return $this->reporter;
    }

    public function sourceVerifier(): FixtureSourceVerifier
    {
        return $this->sourceVerifier;
    }

    public function checkRuntime(ConsoleIo $io): bool
    {
        if (function_exists('token_get_all')) {
            return true;
        }

        $io->err("fixture generation requires the tokenizer extension\n");

        return false;
    }

    public function requiresPhpTestRuntime(): bool
    {
        return true;
    }

    public function rewriteRunner(PhpSrcRoot $phpTestRuntimeRoot): FixtureRewriteRunner
    {
        return new PhptFixtureRewriteRunner(
            phpSrcDir: $phpTestRuntimeRoot->path,
            fixerClasses: [ExceptionOutputFixer::class],
        );
    }
}
