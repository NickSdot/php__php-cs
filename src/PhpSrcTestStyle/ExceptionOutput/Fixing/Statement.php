<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\Classification;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputParts;

final readonly class Statement
{
    public function __construct(
        public int $startOffset,
        public int $endOffset,
        public int $line,
        public string $indent,
        public OutputParts $parts,
        public Classification $classification,
    ) {}
}
