<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Fixing\Rules;

use InternalsCS\Fixers\ExceptionOutput\Analysis\MarkerPrefixPolicy;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputPart;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\Fixers\ExceptionOutput\Fixing\OutputPartMatcher;
use InternalsCS\Fixers\ExceptionOutput\Fixing\OutputStatementBuilder;
use InternalsCS\Fixers\ExceptionOutput\Fixing\RewriteContext;
use InternalsCS\Fixers\ExceptionOutput\Fixing\RewriteRule;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;

use function count;
use function str_ends_with;

final readonly class MarkerPrefixOutputRule implements RewriteRule
{
    public function __construct(
        private OutputStatementBuilder $builder = new OutputStatementBuilder(),
        private MarkerPrefixPolicy $markers = new MarkerPrefixPolicy(),
        private OutputPartMatcher $parts = new OutputPartMatcher(),
    ) {}

    public function rewrite(RewriteContext $context): ?RewriteResult
    {
        $statement = $context->statement;
        $prefixSegments = $this->prefixSegments($statement->parts->parts, $context->catchVariable);

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
    private function prefixSegments(array $parts, string $catchVariable): ?array
    {
        if ($this->isBracketedNumericMessage($parts, $catchVariable)) {
            return [$this->builder->literalSegment($parts[0]->value)];
        }

        if ($this->isErrorNumberVarDump($parts, $catchVariable)) {
            return [$this->builder->literalSegment($parts[0]->value . ': ')];
        }

        if ($this->isVariableClassMessageMarker($parts, $catchVariable)) {
            return [$this->builder->variableSegment((string) $parts[0]->variable), $this->builder->literalSegment($parts[1]->value)];
        }

        return null;
    }

    /** @param list<OutputPart> $parts */
    private function isBracketedNumericMessage(array $parts, string $catchVariable): bool
    {
        if (count($parts) < 2 || !$this->parts->isLiteral($parts[0]) || !$this->parts->isExceptionMessage($parts[1], $catchVariable)) {
            return false;
        }

        if (!$this->markers->isBracketedNumeric($parts[0]->value)) {
            return false;
        }

        return $this->parts->onlyNewlinesAfter($parts, 2);
    }

    /** @param list<OutputPart> $parts */
    private function isErrorNumberVarDump(array $parts, string $catchVariable): bool
    {
        if (2 !== count($parts) || !$this->parts->isLiteral($parts[0]) || !$this->parts->isExceptionMessage($parts[1], $catchVariable)) {
            return false;
        }

        return $this->markers->isErrorNumber($parts[0]->value);
    }

    /** @param list<OutputPart> $parts */
    private function isVariableClassMessageMarker(array $parts, string $catchVariable): bool
    {
        if (count($parts) < 5) {
            return false;
        }

        if (OutputPartKind::OtherVariable !== $parts[0]->kind || null === $parts[0]->variable) {
            return false;
        }

        if (!$this->parts->isLiteral($parts[1]) || !str_ends_with($parts[1]->value, '=>')) {
            return false;
        }

        if (!$this->parts->isExceptionClass($parts[2], $catchVariable)) {
            return false;
        }

        if (!$this->parts->isLiteral($parts[3], ': ')) {
            return false;
        }

        if (!$this->parts->isExceptionMessage($parts[4], $catchVariable)) {
            return false;
        }

        return $this->parts->onlyNewlinesAfter($parts, 5);
    }
}
