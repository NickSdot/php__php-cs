<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use InternalsCS\Console\ConsoleIo;
use InternalsCS\PhpSrc\PhpSrcRoot;
use InternalsCS\SourceFile;

interface FixtureDiscovery
{
    public function fixerName(): string;

    /** @return list<string> */
    public function sourceExtensions(): array;

    public function fixturesDir(string $fixturesRoot): string;

    public function reportsDir(string $reportsRoot): string;

    /** @return list<FixtureCandidate> */
    public function candidates(SourceFile $source): array;

    public function reporter(): ?FixtureReporter;

    public function sourceVerifier(): FixtureSourceVerifier;

    public function checkRuntime(ConsoleIo $io): bool;

    public function requiresPhpTestRuntime(): bool;

    public function rewriteRunner(PhpSrcRoot $phpTestRuntimeRoot): FixtureRewriteRunner;
}
