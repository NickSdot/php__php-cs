<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Generation;

final readonly class PhptSection
{
    public function __construct(
        public string $name,
        public string $contents,
        public int $startLine,
    ) {}
}
