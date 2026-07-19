<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

final readonly class FixtureGenerationJob
{
    public string $fixer;
    public ?FixtureReporter $reporter;
    public string $fixturesDir;
    public ?string $reportsDir;
    public FixtureRewriteRunner $runner;
    public bool $write;
    public bool $refreshOnly;
    public string $rewriteRoot;

    /** @param list<FixtureCandidate> $candidates */
    public function __construct(
        public FixtureDiscovery $discovery,
        public bool $sourceDirty,
        FixtureGenerationOptions $options,
        public int $sourceFileCount,
        public array $candidates,
    ) {
        $this->fixer = $discovery->fixerName();
        $this->reporter = $discovery->reporter();
        $this->fixturesDir = $discovery->fixturesDir($options->fixturesRoot);
        $this->reportsDir = null === $this->reporter ? null : $discovery->reportsDir($options->reportsRoot);
        $this->runner = $discovery->rewriteRunner($options->phpTestRuntimeRoot);
        $this->write = $options->write;
        $this->refreshOnly = $options->refreshOnly;
        $this->rewriteRoot = $options->phpTestRuntimeRoot->path;
    }
}
