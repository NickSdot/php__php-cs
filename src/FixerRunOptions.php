<?php

declare(strict_types=1);

namespace InternalsCS;

final readonly class FixerRunOptions
{
    public function __construct(
        public bool $skipNormalizationOnly = false,
    ) {}
}
