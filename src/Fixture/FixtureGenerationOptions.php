<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use InternalsCS\PhpSrc\PhpSrcRoot;

final readonly class FixtureGenerationOptions
{
    /** @param list<string> $paths */
    public function __construct(
        public PhpSrcRoot $phpSrcRoot,
        public PhpSrcRoot $phpTestRuntimeRoot,
        public string $fixturesRoot,
        public string $reportsRoot,
        public array $paths,
        public bool $allowDirty,
        public bool $write,
        public bool $refreshOnly,
    ) {}
}
