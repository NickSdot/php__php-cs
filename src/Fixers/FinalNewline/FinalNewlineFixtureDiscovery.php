<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\FinalNewline;

use InternalsCS\Console\ConsoleIo;
use InternalsCS\Fixers\FinalNewline\Generation\CandidateCollector;
use InternalsCS\Fixture\FixtureDiscovery;
use InternalsCS\Fixture\FixtureReporter;
use InternalsCS\Fixture\FixtureRewriteRunner;
use InternalsCS\Fixture\FixtureSourceRunVerifier;
use InternalsCS\Fixture\FixtureSourceVerifier;
use InternalsCS\PhpSrc\PhpSrcRoot;
use InternalsCS\PhpSrcTestStyle\PhptFixtureRewriteRunner;
use InternalsCS\SourceFile;

final readonly class FinalNewlineFixtureDiscovery implements FixtureDiscovery
{
    public function __construct(
        private CandidateCollector $candidates = new CandidateCollector(),
        private FixtureSourceVerifier $sourceVerifier = new FixtureSourceRunVerifier(),
    ) {}

    public function fixerName(): string
    {
        return 'final-newline';
    }

    public function sourceExtensions(): array
    {
        return ['phpt'];
    }

    public function fixturesDir(string $fixturesRoot): string
    {
        return $fixturesRoot . '/final_newline';
    }

    public function reportsDir(string $reportsRoot): string
    {
        return $reportsRoot . '/final_newline';
    }

    public function candidates(SourceFile $source): array
    {
        return $this->candidates->collect($source);
    }

    public function reporter(): ?FixtureReporter
    {
        return null;
    }

    public function sourceVerifier(): FixtureSourceVerifier
    {
        return $this->sourceVerifier;
    }

    public function checkRuntime(ConsoleIo $io): bool
    {
        return true;
    }

    public function requiresPhpTestRuntime(): bool
    {
        return true;
    }

    public function rewriteRunner(PhpSrcRoot $phpTestRuntimeRoot): FixtureRewriteRunner
    {
        return new PhptFixtureRewriteRunner(
            phpSrcDir: $phpTestRuntimeRoot->path,
            fixerClasses: [FinalNewlineFixer::class],
        );
    }
}
