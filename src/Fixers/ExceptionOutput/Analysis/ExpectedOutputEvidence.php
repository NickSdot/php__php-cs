<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Analysis;

use function mb_strlen;
use function mb_trim;
use function preg_match;
use function str_contains;
use function str_replace;

final readonly class ExpectedOutputEvidence
{
    public function contains(string $expectedOutput, OutputParts $parts): bool
    {
        $expectedOutput = str_replace(["\r\n", "\r"], "\n", $expectedOutput);

        foreach ($parts->parts as $part) {
            if (OutputPartKind::Literal !== $part->kind) {
                continue;
            }

            $literal = $this->meaningfulLiteral($part->value);

            if (null === $literal) {
                continue;
            }

            return str_contains($expectedOutput, $literal);
        }

        if ($this->hasDumpedExceptionClassEvidence($expectedOutput, $parts)) {
            return true;
        }

        if ($this->hasDumpedExceptionMessageEvidence($expectedOutput, $parts)) {
            return true;
        }

        return !str_contains($parts->shape, 'var_dump') && '' !== mb_trim($expectedOutput);
    }

    private function meaningfulLiteral(string $literal): ?string
    {
        $literal = mb_trim($literal);

        if ('' === $literal || ':' === $literal || '"' === $literal || "'" === $literal) {
            return null;
        }

        if (mb_strlen($literal, '8bit') < 3) {
            return null;
        }

        return $literal;
    }

    private function hasDumpedExceptionClassEvidence(string $expectedOutput, OutputParts $parts): bool
    {
        if (!str_contains($parts->shape, 'var_dump')) {
            return false;
        }

        if (!$parts->has(OutputPartKind::ExceptionClass)) {
            return false;
        }

        if (!str_contains($expectedOutput, 'Exception') && !str_contains($expectedOutput, 'Error')) {
            return false;
        }

        return 1 === preg_match('/^(?:string|%s|%S)\((?:\d+|%d)\) "[A-Za-z_\\\\][A-Za-z0-9_\\\\]*(?:Exception|Error|Throwable|SoapFault)"$/m', $expectedOutput);
    }

    private function hasDumpedExceptionMessageEvidence(string $expectedOutput, OutputParts $parts): bool
    {
        if (!str_contains($parts->shape, 'var_dump')) {
            return false;
        }

        if (!$parts->has(OutputPartKind::ExceptionMessage)) {
            return false;
        }

        if ($parts->has(OutputPartKind::ExceptionClass)) {
            return false;
        }

        return 1 === preg_match('/^(?:string|%s|%S)\((?:\d+|%d)\) ".+"$/m', $expectedOutput);
    }
}
