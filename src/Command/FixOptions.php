<?php

declare(strict_types=1);

namespace InternalsCS\Command;

use InternalsCS\Fixer;
use InternalsCS\PhpSrc\PhpSrcRoot;

final readonly class FixOptions
{
    /**
     * @param list<string> $targets
     * @param list<class-string<Fixer>> $fixerClasses
     */
    public function __construct(
        public PhpSrcRoot $phpSrcRoot,
        public array $targets,
        public array $fixerClasses,
        public bool $check,
        public bool $print,
        public bool $forcePhpBinaryRebuild,
    ) {}
}
