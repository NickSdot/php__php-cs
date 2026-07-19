<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis;

final readonly class Window
{
    public function __construct(
        public int $startOffset,
        public int $endOffset,
        public int $startLine,
        public string $statement,
        public OutputParts $parts,
    ) {}
}
