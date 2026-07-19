<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Fixing\Rules;

use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputPart;
use InternalsCS\Fixers\ExceptionOutput\Fixing\OutputPartMatcher;
use InternalsCS\Fixers\ExceptionOutput\Fixing\OutputStatementBuilder;
use InternalsCS\Fixers\ExceptionOutput\Fixing\RewriteContext;
use InternalsCS\Fixers\ExceptionOutput\Fixing\RewriteRule;
use InternalsCS\Fixers\ExceptionOutput\Fixing\RewriteSafety;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;

use function count;
use function mb_trim;
use function str_replace;

final readonly class ParenthesizedClassLabelOutputRule implements RewriteRule
{
    public function __construct(
        private RewriteSafety $safety = new RewriteSafety(),
        private OutputStatementBuilder $builder = new OutputStatementBuilder(),
        private OutputPartMatcher $parts = new OutputPartMatcher(),
    ) {}

    public function rewrite(RewriteContext $context): ?RewriteResult
    {
        $statement = $context->statement;

        if (!$this->isParenthesizedClassMessage($statement->parts->parts, $context->catchVariable)) {
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
    private function isParenthesizedClassMessage(array $parts, string $catchVariable): bool
    {
        if ($this->isCaughtParenthesizedClassMessage($parts, $catchVariable)) {
            return true;
        }

        if (count($parts) < 4) {
            return false;
        }

        if (!$this->parts->isLiteral($parts[0], 'Exception (')) {
            return false;
        }

        if (!$this->parts->isExceptionClass($parts[1], $catchVariable)) {
            return false;
        }

        if (!$this->parts->isLiteral($parts[2], '): ')) {
            return false;
        }

        if (!$this->parts->isExceptionMessage($parts[3], $catchVariable)) {
            return false;
        }

        return $this->parts->onlyNewlinesAfter($parts, 4);
    }

    /** @param list<OutputPart> $parts */
    private function isCaughtParenthesizedClassMessage(array $parts, string $catchVariable): bool
    {
        if (count($parts) < 5) {
            return false;
        }

        if (!$this->parts->isLiteral($parts[0], 'Caught ')) {
            return false;
        }

        if (!$this->parts->isExceptionClass($parts[1], $catchVariable)) {
            return false;
        }

        if (!$this->parts->isLiteral($parts[2], '(')) {
            return false;
        }

        if (!$this->parts->isExceptionMessage($parts[3], $catchVariable)) {
            return false;
        }

        for ($i = 4; $i < count($parts); $i++) {
            if ($this->isClosingParenOrNewline($parts[$i])) {
                continue;
            }

            return false;
        }

        return true;
    }

    private function isClosingParenOrNewline(OutputPart $part): bool
    {
        if ($this->parts->isNewline($part)) {
            return true;
        }

        if (!$this->parts->isLiteral($part)) {
            return false;
        }

        return ')' === mb_trim(str_replace(["\r", "\n", "\t"], ' ', $part->value));
    }
}
