<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPart;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPartKind;

use function count;
use function preg_match;

final readonly class OutputPartMatcher
{
    public function isNewline(OutputPart $part): bool
    {
        if (OutputPartKind::Newline === $part->kind) {
            return true;
        }

        return OutputPartKind::Literal === $part->kind
            && 1 === preg_match('/^(?:\r\n|\n|\r)+$/', $part->value);
    }

    public function isLiteral(OutputPart $part, ?string $value = null): bool
    {
        if (OutputPartKind::Literal !== $part->kind) {
            return false;
        }

        return null === $value || $part->value === $value;
    }

    public function isExceptionClass(OutputPart $part, string $variable): bool
    {
        return OutputPartKind::ExceptionClass === $part->kind && $part->variable === $variable;
    }

    public function isExceptionMessage(OutputPart $part, string $variable): bool
    {
        return OutputPartKind::ExceptionMessage === $part->kind && $part->variable === $variable;
    }

    public function isExceptionTrace(OutputPart $part, string $variable): bool
    {
        return OutputPartKind::ExceptionTrace === $part->kind && $part->variable === $variable;
    }

    /** @param list<OutputPart> $parts */
    public function exceptionMessageOffset(array $parts, string $variable): ?int
    {
        foreach ($parts as $i => $part) {
            if ($this->isExceptionMessage($part, $variable)) {
                return $i;
            }
        }

        return null;
    }

    /** @param list<OutputPart> $parts */
    public function onlyNewlinesAfter(array $parts, int $offset): bool
    {
        for ($i = $offset; $i < count($parts); $i++) {
            if (!$this->isNewline($parts[$i])) {
                return false;
            }
        }

        return true;
    }
}
