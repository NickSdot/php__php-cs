<?php

declare(strict_types=1);

namespace InternalsCS\Command;

use InternalsCS\PhpSrc\PhpSrcRoot;

final readonly class GenerateOptions
{
    /** @param list<string> $paths */
    public function __construct(
        public PhpSrcRoot $phpSrcRoot,
        public PhpSrcRoot $phpTestRuntimeRoot,
        public string $fixturesDir,
        public string $reportsDir,
        public array $paths,
        public bool $allowDirty,
        public bool $write,
        public bool $forcePhpBinaryRebuild,
        public bool $refreshOnly,
    ) {}

    public function withPhpTestRuntimeRoot(PhpSrcRoot $phpTestRuntimeRoot): self
    {
        return new self(
            phpSrcRoot: $this->phpSrcRoot,
            phpTestRuntimeRoot: $phpTestRuntimeRoot,
            fixturesDir: $this->fixturesDir,
            reportsDir: $this->reportsDir,
            paths: $this->paths,
            allowDirty: $this->allowDirty,
            write: $this->write,
            forcePhpBinaryRebuild: $this->forcePhpBinaryRebuild,
            refreshOnly: $this->refreshOnly,
        );
    }
}
