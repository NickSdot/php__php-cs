<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputFamily;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalRewriteSafety;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalStatementBuilder;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteContext;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Statement;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;

abstract readonly class SingleStatementOutputRule implements RewriteRule
{
    public function __construct(
        private CanonicalRewriteSafety $safety = new CanonicalRewriteSafety(),
        private CanonicalStatementBuilder $builder = new CanonicalStatementBuilder(),
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
