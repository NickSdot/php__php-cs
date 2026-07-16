<?php

declare(strict_types=1);

namespace InternalsCS\Command;

use InternalsCS\PhpSrc\PhpSrcRoot;

final readonly class FixOptions
{
    /** @param list<string> $targets */
    public function __construct(
        public PhpSrcRoot $phpSrcRoot,
        public array $targets,
        public bool $check,
        public bool $print,
        public bool $forcePhpBinaryRebuild,
    ) {}
}
