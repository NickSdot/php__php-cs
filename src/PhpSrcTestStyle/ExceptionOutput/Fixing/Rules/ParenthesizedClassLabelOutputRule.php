<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPart;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalRewriteSafety;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalStatementBuilder;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteContext;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteRule;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;

use function count;

final readonly class ParenthesizedClassLabelOutputRule implements RewriteRule
{
    public function __construct(
        private CanonicalRewriteSafety $safety = new CanonicalRewriteSafety(),
        private CanonicalStatementBuilder $builder = new CanonicalStatementBuilder(),
    ) {}

    public function rewrite(RewriteContext $context): ?RewriteResult
    {
        $statement = $context->statement;

        if (!$this->isParenthesizedClassMessage($statement->parts->parts)) {
            return null;
        }

        if ($statement->parts->hasUnknown()) {
            return null;
        }

        if (!$this->safety->usesOnlyVariable($statement->parts, $context->catchVariable)) {
            return null;
        }

        return new RewriteResult(new TextEdit(
            startOffset: $statement->startOffset,
            endOffset: $statement->endOffset,
            line: $statement->line,
            replacement: $this->builder->build($context->catchVariable, $statement->parts),
        ));
    }

    /** @param list<OutputPart> $parts */
    private function isParenthesizedClassMessage(array $parts): bool
    {
        if (count($parts) < 4) {
            return false;
        }

        if (!$this->isLiteral($parts[0], 'Exception (')) {
            return false;
        }

        if (OutputPartKind::ExceptionClass !== $parts[1]->kind) {
            return false;
        }

        if (!$this->isLiteral($parts[2], '): ')) {
            return false;
        }

        if (OutputPartKind::ExceptionMessage !== $parts[3]->kind) {
            return false;
        }

        for ($i = 4; $i < count($parts); $i++) {
            if (OutputPartKind::Newline !== $parts[$i]->kind) {
                return false;
            }
        }

        return true;
    }

    private function isLiteral(OutputPart $part, string $value): bool
    {
        return OutputPartKind::Literal === $part->kind && $part->value === $value;
    }
}
