<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Fixing;

use InternalsCS\Fixers\ExceptionOutput\Analysis\ClassificationSafety;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputFamily;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputParts;

use function in_array;

final readonly class RewriteSafety
{
    public function canRewrite(Statement $statement, string $catchVariable, OutputFamily ...$families): bool
    {
        return $this->canRewriteStatement($statement, $catchVariable, false, $families);
    }

    public function canRewriteTraceOutput(Statement $statement, string $catchVariable, OutputFamily ...$families): bool
    {
        return $this->canRewriteStatement($statement, $catchVariable, true, $families);
    }

    /** @param array<array-key, OutputFamily> $families */
    private function canRewriteStatement(Statement $statement, string $catchVariable, bool $allowTrace, array $families): bool
    {
        $classification = $statement->classification;

        if (ClassificationSafety::Fixable !== $classification->safety) {
            return false;
        }

        if (!in_array($classification->family, $families, true)) {
            return false;
        }

        return $this->canRewriteMessageOutput($statement->parts, $catchVariable, $allowTrace);
    }

    public function isClassOnlyOutput(Statement $statement, string $catchVariable): bool
    {
        $parts = $statement->parts;

        if ($parts->hasUnknown()) {
            return false;
        }

        if (!$parts->has(OutputPartKind::ExceptionClass)) {
            return false;
        }

        if ($parts->has(OutputPartKind::ExceptionMessage)) {
            return false;
        }

        if ($parts->has(OutputPartKind::ExceptionFile)) {
            return false;
        }

        if ($parts->has(OutputPartKind::ExceptionLine)) {
            return false;
        }

        if (!$this->canBuildStandardOutput($parts)) {
            return false;
        }

        return $this->usesOnlyVariable($parts, $catchVariable);
    }

    public function canBuildStandardOutput(OutputParts $parts): bool
    {
        return !$parts->has(OutputPartKind::ExceptionTrace);
    }

    private function canRewriteMessageOutput(OutputParts $parts, string $catchVariable, bool $allowTrace = false): bool
    {
        if ($parts->hasUnknown()) {
            return false;
        }

        if (!$parts->has(OutputPartKind::ExceptionMessage)) {
            return false;
        }

        if (!$allowTrace && !$this->canBuildStandardOutput($parts)) {
            return false;
        }

        return $this->usesOnlyVariable($parts, $catchVariable);
    }

    public function usesOnlyVariable(OutputParts $parts, string $catchVariable): bool
    {
        foreach ($parts->parts as $part) {
            if (OutputPartKind::OtherVariable === $part->kind || OutputPartKind::OtherExpression === $part->kind) {
                return false;
            }

            if (null === $part->variable) {
                continue;
            }

            if ($part->variable !== $catchVariable) {
                return false;
            }
        }

        return true;
    }
}
