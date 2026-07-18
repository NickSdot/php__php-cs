<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputFamily;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalRewriteSafety;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalStatementBuilder;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\OutputPartMatcher;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteContext;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Statement;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;

final readonly class AdjacentMessageThenNewlineOutputRule implements RewriteRule
{
    public function __construct(
        private CanonicalRewriteSafety $safety = new CanonicalRewriteSafety(),
        private CanonicalStatementBuilder $builder = new CanonicalStatementBuilder(),
        private OutputPartMatcher $parts = new OutputPartMatcher(),
    ) {}

    public function rewrite(RewriteContext $context): ?RewriteResult
    {
        $message = $context->statement;
        $newline = $context->nextStatement;

        if (null === $newline) {
            return null;
        }

        if ($message->parts->has(OutputPartKind::Newline)) {
            return null;
        }

        if (!$this->isNewlineOnlyOutput($newline)) {
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
                startOffset: $message->startOffset,
                endOffset: $newline->endOffset,
                line: $message->line,
                replacement: $this->builder->build($context->catchVariable, $message->parts),
            ),
            consumedStatements: 2,
        );
    }

    private function isNewlineOnlyOutput(Statement $statement): bool
    {
        foreach ($statement->parts->parts as $part) {
            if (!$this->parts->isNewline($part)) {
                return false;
            }
        }

        return [] !== $statement->parts->parts;
    }
}
