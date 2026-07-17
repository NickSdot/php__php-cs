<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPart;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputParts;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalRewriteSafety;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalStatementBuilder;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteContext;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteRule;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;

final readonly class CatchTypeLabelOutputRule implements RewriteRule
{
    public function __construct(
        private CanonicalRewriteSafety $safety = new CanonicalRewriteSafety(),
        private CanonicalStatementBuilder $builder = new CanonicalStatementBuilder(),
        private CatchTypeLabels $catchTypeLabels = new CatchTypeLabels(),
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

        if (!$statement->parts->has(OutputPartKind::ExceptionMessage)) {
            return null;
        }

        if (!$this->safety->usesOnlyVariable($statement->parts, $context->catchVariable)) {
            return null;
        }

        if (!$this->hasOnlyCatchTypeLabelAndMessage($statement->parts, $context->catchTypes)) {
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
    private function hasOnlyCatchTypeLabelAndMessage(OutputParts $parts, array $catchTypes): bool
    {
        $matchedLabel = false;
        $matchedMessage = false;

        foreach ($parts->parts as $part) {
            if (OutputPartKind::ExceptionMessage === $part->kind) {
                if ($matchedMessage) {
                    return false;
                }

                $matchedMessage = true;
                continue;
            }

            if (OutputPartKind::Newline === $part->kind && $matchedMessage) {
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

        if (OutputPartKind::Literal !== $part->kind) {
            return false;
        }

        return $this->catchTypeLabels->contains($catchTypes, $part->value);
    }
}
