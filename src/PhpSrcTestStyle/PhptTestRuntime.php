<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle;

final readonly class PhptTestRuntime
{
    public function __construct(
        public string $phpBinary,
        public ?string $phpCgiBinary,
    ) {}
}
