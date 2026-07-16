<?php

declare(strict_types=1);

namespace InternalsCS\Command;

use InternalsCS\PhpSrc\PhpSrcRoot;

final readonly class GenerateOptions
{
    /** @param list<string> $paths */
    public function __construct(
        public PhpSrcRoot $phpSrcRoot,
        public string $fixturesDir,
        public string $reportsDir,
        public array $paths,
        public bool $allowDirty,
        public bool $write,
        public bool $forcePhpBinaryRebuild,
    ) {}
}
