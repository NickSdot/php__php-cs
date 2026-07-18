<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\ClassificationSafety;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPart;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalStatementBuilder;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\OutputPartMatcher;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteContext;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteRule;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;

use function array_any;
use function count;
use function mb_trim;
use function preg_replace;

final readonly class DynamicContextPrefixOutputRule implements RewriteRule
{
    public function __construct(
        private CanonicalStatementBuilder $builder = new CanonicalStatementBuilder(),
        private OutputPartMatcher $parts = new OutputPartMatcher(),
    ) {}

    public function rewrite(RewriteContext $context): ?RewriteResult
    {
        $statement = $context->statement;
        $prefixSegments = $this->prefixSegments(
            parts: $statement->parts->parts,
            catchVariable: $context->catchVariable,
            safety: $statement->classification->safety,
        );

        if (null === $prefixSegments) {
            return null;
        }

        return new RewriteResult(new TextEdit(
            startOffset: $statement->startOffset,
            endOffset: $statement->endOffset,
            line: $statement->line,
            replacement: $this->builder->buildWithPrefixSegments($context->catchVariable, $statement->parts, $prefixSegments),
        ));
    }

    /**
     * @param list<OutputPart> $parts
     * @return list<string>|null
     */
    private function prefixSegments(array $parts, string $catchVariable, ClassificationSafety $safety): ?array
    {
        $messageOffset = $this->parts->exceptionMessageOffset($parts, $catchVariable);

        if (null === $messageOffset || !$this->parts->onlyNewlinesAfter($parts, $messageOffset + 1)) {
            return null;
        }

        $prefixParts = [];

        for ($i = 0; $i < $messageOffset; $i++) {
            $prefixParts[] = $parts[$i];
        }

        $prefixParts = $this->normalizedPrefix($prefixParts);

        if (null === $prefixParts || !$this->isContextPrefix($prefixParts, $safety)) {
            return null;
        }

        $interpolatedSegment = $this->builder->interpolatedSegment($prefixParts);

        if (null !== $interpolatedSegment) {
            return [$interpolatedSegment];
        }

        $segments = [];

        foreach ($prefixParts as $part) {
            $segments[] = $this->segment($part);
        }

        return $segments;
    }

    /**
     * @param list<OutputPart> $parts
     * @return list<OutputPart>|null
     */
    private function normalizedPrefix(array $parts): ?array
    {
        $lastLiteral = $this->lastLiteralOffset($parts);
        $normalized = [];

        foreach ($parts as $i => $part) {
            if (OutputPartKind::Literal !== $part->kind) {
                $normalized[] = $part;
                continue;
            }

            $value = $i === $lastLiteral
                ? $this->stripTrailingExceptionMarker($part->value)
                : $part->value;

            if ('' === $value) {
                continue;
            }

            $normalized[] = OutputPart::literal($value, $part->source);
        }

        return [] === $normalized ? null : $normalized;
    }

    /** @param list<OutputPart> $parts */
    private function lastLiteralOffset(array $parts): ?int
    {
        for ($i = count($parts) - 1; $i >= 0; $i--) {
            if (OutputPartKind::Literal === $parts[$i]->kind) {
                return $i;
            }
        }

        return null;
    }

    private function stripTrailingExceptionMarker(string $literal): string
    {
        $withoutThrownMarker = preg_replace('/\s+threw\s*$/i', ' ', $literal, count: $count);

        if (1 === $count) {
            return (string) $withoutThrownMarker;
        }

        $withoutColonMarker = preg_replace('/:\s*(?:exception|error)\s*[-:]\s*$/i', ': ', $literal, count: $count);

        if (1 === $count) {
            return (string) $withoutColonMarker;
        }

        return (string) preg_replace('/\s+(?:exception|error)\s*[-:]\s*$/i', ': ', $literal);
    }

    /** @param list<OutputPart> $parts */
    private function isContextPrefix(array $parts, ClassificationSafety $safety): bool
    {
        if ([] === $parts) {
            return false;
        }

        if (!$this->hasDynamicContext($parts) && ClassificationSafety::DescriptiveContext !== $safety) {
            return false;
        }

        foreach ($parts as $part) {
            if (!$this->isContextPrefixPart($part)) {
                return false;
            }
        }

        return !$this->isBlankLiteralPrefix($parts);
    }

    /** @param list<OutputPart> $parts */
    private function hasDynamicContext(array $parts): bool
    {
        return array_any($parts, fn($part) => OutputPartKind::OtherVariable === $part->kind || OutputPartKind::OtherExpression === $part->kind);
    }

    private function isContextPrefixPart(OutputPart $part): bool
    {
        return match ($part->kind) {
            OutputPartKind::Literal,
            OutputPartKind::OtherVariable,
            OutputPartKind::OtherExpression => true,
            default => false,
        };
    }

    /** @param list<OutputPart> $parts */
    private function isBlankLiteralPrefix(array $parts): bool
    {
        if (1 !== count($parts) || OutputPartKind::Literal !== $parts[0]->kind) {
            return false;
        }

        return '' === mb_trim($parts[0]->value);
    }

    private function segment(OutputPart $part): string
    {
        if (OutputPartKind::OtherVariable === $part->kind) {
            return $this->builder->variableSegment((string) $part->variable);
        }

        if (OutputPartKind::OtherExpression === $part->kind) {
            return (string) $part->source;
        }

        return $this->builder->literalSegment($part->value);
    }
}
