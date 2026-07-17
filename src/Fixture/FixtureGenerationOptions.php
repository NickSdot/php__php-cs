<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

final readonly class FixtureGenerationOptions
{
    /**
     * @param list<string> $paths
     * @param list<string> $excludedRoots
     * @param list<string> $extensions
     */
    public function __construct(
        public string $sourceRoot,
        public string $fixturesDir,
        public string $reportsDir,
        public array $paths,
        public array $excludedRoots,
        public array $extensions,
        public FixtureRewriteRunner $runner,
        public bool $allowDirty,
        public bool $sourceDirty,
        public bool $write,
        public bool $refreshOnly,
    ) {}
}
