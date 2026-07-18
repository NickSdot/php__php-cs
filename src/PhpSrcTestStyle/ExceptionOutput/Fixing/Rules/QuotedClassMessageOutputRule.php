<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputFamily;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPart;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalRewriteSafety;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalStatementBuilder;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\OutputPartMatcher;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteContext;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteRule;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;

use function count;
use function in_array;
use function mb_trim;

final readonly class QuotedClassMessageOutputRule implements RewriteRule
{
    public function __construct(
        private CanonicalRewriteSafety $safety = new CanonicalRewriteSafety(),
        private CanonicalStatementBuilder $builder = new CanonicalStatementBuilder(),
        private OutputPartMatcher $parts = new OutputPartMatcher(),
    ) {}

    public function rewrite(RewriteContext $context): ?RewriteResult
    {
        $statement = $context->statement;

        if (!$this->isQuotedClassMessage($statement->parts->parts, $context->catchVariable)) {
            return null;
        }

        if (OutputFamily::ClassMessage !== $statement->classification->family) {
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
    private function isQuotedClassMessage(array $parts, string $catchVariable): bool
    {
        if (count($parts) < 4) {
            return false;
        }

        if (!$this->parts->isExceptionClass($parts[0], $catchVariable)) {
            return false;
        }

        if (!in_array($parts[1]->value, [': \'', ': "'], true)) {
            return false;
        }

        if (!$this->parts->isExceptionMessage($parts[2], $catchVariable)) {
            return false;
        }

        for ($i = 3; $i < count($parts); $i++) {
            if ($this->isClosingQuoteOrNewline($parts[$i])) {
                continue;
            }

            return false;
        }

        return true;
    }

    private function isClosingQuoteOrNewline(OutputPart $part): bool
    {
        if ($this->parts->isNewline($part)) {
            return true;
        }

        if (!$this->parts->isLiteral($part)) {
            return false;
        }

        return in_array(mb_trim($part->value), ['\'', '"'], true);
    }
}
