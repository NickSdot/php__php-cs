<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Fixing;

use InternalsCS\Fixers\ExceptionOutput\Analysis\Classification;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputParts;

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
