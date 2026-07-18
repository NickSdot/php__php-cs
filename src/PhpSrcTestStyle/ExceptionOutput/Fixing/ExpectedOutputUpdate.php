<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing;

final readonly class ExpectedOutputUpdate
{
    private function __construct(
        public ?string $output,
        public ?string $failure,
    ) {}

    public static function changed(string $output): self
    {
        return new self($output, null);
    }

    public static function failed(string $failure): self
    {
        return new self(null, $failure);
    }
}
