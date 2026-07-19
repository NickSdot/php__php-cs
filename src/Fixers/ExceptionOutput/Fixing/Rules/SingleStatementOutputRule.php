<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Fixing\Rules;

use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputFamily;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\Fixers\ExceptionOutput\Fixing\OutputStatementBuilder;
use InternalsCS\Fixers\ExceptionOutput\Fixing\RewriteContext;
use InternalsCS\Fixers\ExceptionOutput\Fixing\RewriteRule;
use InternalsCS\Fixers\ExceptionOutput\Fixing\RewriteSafety;
use InternalsCS\Fixers\ExceptionOutput\Fixing\Statement;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;

abstract readonly class SingleStatementOutputRule implements RewriteRule
{
    public function __construct(
        private RewriteSafety $safety = new RewriteSafety(),
        private OutputStatementBuilder $builder = new OutputStatementBuilder(),
    ) {}

    final public function rewrite(RewriteContext $context): ?RewriteResult
    {
        $statement = $context->statement;

        if (!$this->accepts($statement)) {
            return null;
        }

        if (!$this->safety->canRewrite($statement, $context->catchVariable, ...$this->families())) {
            return null;
        }

        return new RewriteResult(new TextEdit(
            startOffset: $statement->startOffset,
            endOffset: $statement->endOffset,
            line: $statement->line,
            replacement: $this->builder->build($context->catchVariable, $statement->parts),
        ));
    }

    /** @return non-empty-list<OutputFamily> */
    abstract protected function families(): array;

    abstract protected function accepts(Statement $statement): bool;

    protected function hasLocation(Statement $statement): bool
    {
        return $statement->parts->has(OutputPartKind::ExceptionFile)
            || $statement->parts->has(OutputPartKind::ExceptionLine);
    }
}
