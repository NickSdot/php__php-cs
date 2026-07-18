<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputFamily;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\OutputStatementBuilder;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteContext;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteSafety;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;

final readonly class AdjacentClassThenMessageOutputRule implements RewriteRule
{
    public function __construct(
        private RewriteSafety $safety = new RewriteSafety(),
        private OutputStatementBuilder $builder = new OutputStatementBuilder(),
    ) {}

    public function rewrite(RewriteContext $context): ?RewriteResult
    {
        $message = $context->nextStatement;

        if (null === $message) {
            return null;
        }

        if (!$this->safety->isClassOnlyOutput($context->statement, $context->catchVariable)) {
            return null;
        }

        if (!$this->safety->canRewrite(
            $message,
            $context->catchVariable,
            OutputFamily::MessageOnly,
            OutputFamily::ClassMessage,
            OutputFamily::ClassMessageLocation,
        )) {
            return null;
        }

        return new RewriteResult(
            edit: new TextEdit(
                startOffset: $context->statement->startOffset,
                endOffset: $message->endOffset,
                line: $context->statement->line,
                replacement: $this->builder->build($context->catchVariable, $message->parts),
            ),
            consumedStatements: 2,
        );
    }
}
