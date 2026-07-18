<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrc;

final readonly class PhpBuildProcessResult
{
    public function __construct(
        public int $exitCode,
        public string $stderr,
    ) {}

    public function ok(): bool
    {
        return 0 === $this->exitCode;
    }
}
