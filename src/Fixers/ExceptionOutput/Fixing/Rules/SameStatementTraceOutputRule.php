<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Fixing\Rules;

use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputFamily;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputPart;
use InternalsCS\Fixers\ExceptionOutput\Fixing\OutputPartMatcher;
use InternalsCS\Fixers\ExceptionOutput\Fixing\OutputStatementBuilder;
use InternalsCS\Fixers\ExceptionOutput\Fixing\RewriteContext;
use InternalsCS\Fixers\ExceptionOutput\Fixing\RewriteRule;
use InternalsCS\Fixers\ExceptionOutput\Fixing\RewriteSafety;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;

use function count;
use function mb_strlen;
use function strspn;

final readonly class SameStatementTraceOutputRule implements RewriteRule
{
    public function __construct(
        private RewriteSafety $safety = new RewriteSafety(),
        private OutputStatementBuilder $builder = new OutputStatementBuilder(),
        private OutputPartMatcher $parts = new OutputPartMatcher(),
    ) {}

    public function rewrite(RewriteContext $context): ?RewriteResult
    {
        $statement = $context->statement;

        if (!$this->safety->canRewrite($statement, $context->catchVariable, OutputFamily::MessageOnly)) {
            return null;
        }

        $prefix = $this->literalIndentPrefix($statement->parts->parts, $context->catchVariable);

        if (null === $prefix) {
            return null;
        }

        return new RewriteResult(new TextEdit(
            startOffset: $statement->startOffset,
            endOffset: $statement->endOffset,
            line: $statement->line,
            replacement: $this->builder->buildSameStatementTrace($context->catchVariable, $prefix),
        ));
    }

    /** @param list<OutputPart> $parts */
    private function literalIndentPrefix(array $parts, string $catchVariable): ?string
    {
        if (4 !== count($parts)) {
            return null;
        }

        if (!$this->parts->isLiteral($parts[0]) || strspn($parts[0]->value, " \t") !== mb_strlen($parts[0]->value)) {
            return null;
        }

        if (!$this->parts->isExceptionMessage($parts[1], $catchVariable)) {
            return null;
        }

        if (!$this->parts->isNewline($parts[2])) {
            return null;
        }

        return $this->parts->isExceptionTrace($parts[3], $catchVariable) ? $parts[0]->value : null;
    }
}
