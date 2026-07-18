<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\ClassificationSafety;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputFamily;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputParts;

use function in_array;

final readonly class RewriteSafety
{
    public function canRewrite(Statement $statement, string $catchVariable, OutputFamily ...$families): bool
    {
        $classification = $statement->classification;

        if (ClassificationSafety::Fixable !== $classification->safety) {
            return false;
        }

        if (!in_array($classification->family, $families, true)) {
            return false;
        }

        return $this->canRewriteMessageOutput($statement->parts, $catchVariable);
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

        return $this->usesOnlyVariable($parts, $catchVariable);
    }

    private function canRewriteMessageOutput(OutputParts $parts, string $catchVariable): bool
    {
        if ($parts->hasUnknown()) {
            return false;
        }

        if (!$parts->has(OutputPartKind::ExceptionMessage)) {
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
