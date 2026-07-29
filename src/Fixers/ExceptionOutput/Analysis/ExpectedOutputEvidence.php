<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Analysis;

use InternalsCS\Support\Whitespace;

use function mb_strlen;
use function mb_trim;
use function preg_match;
use function str_contains;

final readonly class ExpectedOutputEvidence
{
    public function contains(string $expectedOutput, OutputParts $parts): bool
    {
        $expectedOutput = Whitespace::normalizeLineEndingsToLf($expectedOutput);

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

        if ($parts->has(OutputPartKind::ExceptionTrace)) {
            return 1 === preg_match('/^#\d+(?:\s|$)/m', $expectedOutput);
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

        if (1 !== preg_match('/exception|error/i', $expectedOutput)) {
            return false;
        }

        return 1 === preg_match('/^(?:string|%s|%S)\((?:\d+|%d)\) "[A-Za-z_\\\\][A-Za-z0-9_\\\\]*(?:Exception|Error|Throwable|SoapFault)"$/mi', $expectedOutput);
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
