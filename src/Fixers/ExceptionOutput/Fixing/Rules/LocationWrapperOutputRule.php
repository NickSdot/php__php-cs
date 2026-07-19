<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Fixing\Rules;

use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputFamily;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputPart;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\Fixers\ExceptionOutput\Analysis\TrashLiteralPolicy;
use InternalsCS\Fixers\ExceptionOutput\Fixing\OutputPartMatcher;
use InternalsCS\Fixers\ExceptionOutput\Fixing\OutputStatementBuilder;
use InternalsCS\Fixers\ExceptionOutput\Fixing\RewriteContext;
use InternalsCS\Fixers\ExceptionOutput\Fixing\RewriteRule;
use InternalsCS\Fixers\ExceptionOutput\Fixing\RewriteSafety;
use InternalsCS\Fixers\ExceptionOutput\Fixing\Statement;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;

use function in_array;
use function mb_strtolower;
use function mb_trim;
use function str_replace;

final readonly class LocationWrapperOutputRule implements RewriteRule
{
    public function __construct(
        private RewriteSafety $safety = new RewriteSafety(),
        private OutputStatementBuilder $builder = new OutputStatementBuilder(),
        private TrashLiteralPolicy $trash = new TrashLiteralPolicy(),
        private OutputPartMatcher $parts = new OutputPartMatcher(),
    ) {}

    public function rewrite(RewriteContext $context): ?RewriteResult
    {
        $statement = $context->statement;

        if (!$this->hasLocation($statement)) {
            return null;
        }

        if (!$statement->parts->has(OutputPartKind::ExceptionMessage)) {
            return null;
        }

        if ($statement->parts->hasUnknown()) {
            return null;
        }

        if (!$this->safety->usesOnlyVariable($statement->parts, $context->catchVariable)) {
            return null;
        }

        if (!$this->hasSupportedLocationWrappers($statement)) {
            return null;
        }

        if (!in_array($statement->classification->family, [OutputFamily::MessageOnly, OutputFamily::ClassMessageLocation], true)) {
            return null;
        }

        return new RewriteResult(new TextEdit(
            startOffset: $statement->startOffset,
            endOffset: $statement->endOffset,
            line: $statement->line,
            replacement: $this->builder->build($context->catchVariable, $statement->parts),
        ));
    }

    private function hasLocation(Statement $statement): bool
    {
        return $statement->parts->has(OutputPartKind::ExceptionFile)
            || $statement->parts->has(OutputPartKind::ExceptionLine);
    }

    private function hasSupportedLocationWrappers(Statement $statement): bool
    {
        foreach ($statement->parts->parts as $part) {
            if (!$this->parts->isLiteral($part)) {
                continue;
            }

            if ($this->isSupportedLiteral($part)) {
                continue;
            }

            return false;
        }

        return true;
    }

    private function isSupportedLiteral(OutputPart $part): bool
    {
        if ($this->trash->isTrash($part->value)) {
            return true;
        }

        return in_array($this->normalized($part->value), [':', 'at', 'in', 'on line', '-', '(', ')'], true);
    }

    private function normalized(string $literal): string
    {
        $label = str_replace(["\r", "\n", "\t"], ' ', $literal);
        $label = mb_trim($label);

        return mb_strtolower($label);
    }
}
