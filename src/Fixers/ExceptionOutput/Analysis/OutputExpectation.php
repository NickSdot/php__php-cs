<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Analysis;

final readonly class OutputExpectation
{
    /** @param array<string, string> $values */
    public function __construct(
        private array $values = [],
    ) {}

    public function value(OutputPartKind $kind): ?string
    {
        return $this->values[$kind->value] ?? null;
    }

    public function isEmpty(OutputPartKind $kind): bool
    {
        return '' === $this->value($kind);
    }

    /** @return array<string, string> */
    public function values(): array
    {
        return $this->values;
    }
}
