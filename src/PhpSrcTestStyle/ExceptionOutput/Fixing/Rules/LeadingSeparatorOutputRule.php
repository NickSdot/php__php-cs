<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalStatementBuilder;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\LeadingSeparatorOutput;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteContext;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteRule;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;

final readonly class LeadingSeparatorOutputRule implements RewriteRule
{
    public function __construct(
        private LeadingSeparatorOutput $leadingSeparator = new LeadingSeparatorOutput(),
        private CanonicalStatementBuilder $builder = new CanonicalStatementBuilder(),
    ) {}

    public function rewrite(RewriteContext $context): ?RewriteResult
    {
        $statement = $context->statement;

        if (!$this->leadingSeparator->matches($statement, $context->catchVariable)) {
            return null;
        }

        return new RewriteResult(new TextEdit(
            startOffset: $statement->startOffset,
            endOffset: $statement->endOffset,
            line: $statement->line,
            replacement: $this->builder->buildWithPrefixSegments($context->catchVariable, $statement->parts, ['\\PHP_EOL']),
        ));
    }
}
