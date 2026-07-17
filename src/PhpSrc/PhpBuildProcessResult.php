<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrc;

use function mb_trim;

final readonly class PhpBuildProcessResult
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
    ) {}

    public function ok(): bool
    {
        return 0 === $this->exitCode;
    }

    public function stdout(): string
    {
        return mb_trim($this->stdout);
    }
}
