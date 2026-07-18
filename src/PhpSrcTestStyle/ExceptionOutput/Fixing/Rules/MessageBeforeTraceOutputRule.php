<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputFamily;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalRewriteSafety;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\OutputPartMatcher;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteContext;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteRule;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;

final readonly class MessageBeforeTraceOutputRule implements RewriteRule
{
    public function __construct(
        private CanonicalRewriteSafety $safety = new CanonicalRewriteSafety(),
        private OutputPartMatcher $parts = new OutputPartMatcher(),
    ) {}

    public function rewrite(RewriteContext $context): ?RewriteResult
    {
        if (null === $context->nextStatement || !$this->isTraceOutput($context)) {
            return null;
        }

        if (!$this->safety->canRewrite($context->statement, $context->catchVariable, OutputFamily::MessageOnly)) {
            return null;
        }

        return new RewriteResult(new TextEdit(
            startOffset: $context->statement->startOffset,
            endOffset: $context->statement->endOffset,
            line: $context->statement->line,
            replacement: 'echo $' . $context->catchVariable . '::class, \': \', $' . $context->catchVariable . '->getMessage();',
        ));
    }

    private function isTraceOutput(RewriteContext $context): bool
    {
        $parts = $context->nextStatement?->parts->parts ?? [];

        return 1 === count($parts)
            && $this->parts->isExceptionTrace($parts[0], $context->catchVariable);
    }
}
