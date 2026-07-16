<?php

declare(strict_types=1);

namespace InternalsCS;

final readonly class TextEdit
{
    public function __construct(
        public int $startOffset,
        public int $endOffset,
        public int $line,
        public string $replacement,
    ) {}
}
