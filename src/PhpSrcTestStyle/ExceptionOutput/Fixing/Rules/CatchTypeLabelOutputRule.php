<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPart;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputParts;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\OutputPartMatcher;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\OutputStatementBuilder;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteContext;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteSafety;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;

final readonly class CatchTypeLabelOutputRule implements RewriteRule
{
    public function __construct(
        private RewriteSafety $safety = new RewriteSafety(),
        private OutputStatementBuilder $builder = new OutputStatementBuilder(),
        private CatchTypeLabels $catchTypeLabels = new CatchTypeLabels(),
        private OutputPartMatcher $partMatcher = new OutputPartMatcher(),
    ) {}

    public function rewrite(RewriteContext $context): ?RewriteResult
    {
        $statement = $context->statement;

        if ([] === $context->catchTypes) {
            return null;
        }

        if ($statement->parts->hasUnknown()) {
            return null;
        }

        if (null === $this->partMatcher->exceptionMessageOffset($statement->parts->parts, $context->catchVariable)) {
            return null;
        }

        if (!$this->safety->usesOnlyVariable($statement->parts, $context->catchVariable)) {
            return null;
        }

        if (!$this->hasOnlyCatchTypeLabelAndMessage($statement->parts, $context->catchTypes, $context->catchVariable)) {
            return null;
        }

        return new RewriteResult(new TextEdit(
            startOffset: $statement->startOffset,
            endOffset: $statement->endOffset,
            line: $statement->line,
            replacement: $this->builder->build($context->catchVariable, $statement->parts),
        ));
    }

    /** @param list<string> $catchTypes */
    private function hasOnlyCatchTypeLabelAndMessage(OutputParts $parts, array $catchTypes, string $catchVariable): bool
    {
        $matchedLabel = false;
        $matchedMessage = false;

        foreach ($parts->parts as $part) {
            if ($this->partMatcher->isExceptionMessage($part, $catchVariable)) {
                if ($matchedMessage) {
                    return false;
                }

                $matchedMessage = true;
                continue;
            }

            if ($this->partMatcher->isNewline($part) && $matchedMessage) {
                continue;
            }

            if ($this->isCatchTypeLabelBeforeMessage($part, $catchTypes, $matchedMessage, $matchedLabel)) {
                $matchedLabel = true;
                continue;
            }

            return false;
        }

        return $matchedLabel && $matchedMessage;
    }

    /** @param list<string> $catchTypes */
    private function isCatchTypeLabelBeforeMessage(
        OutputPart $part,
        array $catchTypes,
        bool $matchedMessage,
        bool $matchedLabel,
    ): bool {
        if ($matchedMessage || $matchedLabel) {
            return false;
        }

        if (!$this->partMatcher->isLiteral($part)) {
            return false;
        }

        return $this->catchTypeLabels->contains($catchTypes, $part->value);
    }
}
