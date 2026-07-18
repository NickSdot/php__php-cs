<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Generation;

final readonly class PhptSection
{
    public function __construct(
        public string $contents,
        public int $startLine,
    ) {}
}
