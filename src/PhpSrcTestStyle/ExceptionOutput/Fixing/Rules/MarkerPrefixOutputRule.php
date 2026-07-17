<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\MarkerPrefixPolicy;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPart;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalStatementBuilder;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteContext;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteRule;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;

use function count;
use function str_ends_with;
use function str_replace;

final readonly class MarkerPrefixOutputRule implements RewriteRule
{
    public function __construct(
        private CanonicalStatementBuilder $builder = new CanonicalStatementBuilder(),
        private MarkerPrefixPolicy $markers = new MarkerPrefixPolicy(),
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
            return [$this->quote($parts[0]->value)];
        }

        if ($this->isErrorNumberVarDump($parts, $catchVariable)) {
            return [$this->quote($parts[0]->value . ': ')];
        }

        if ($this->isVariableClassMessageMarker($parts, $catchVariable)) {
            return ['$' . $parts[0]->variable, $this->quote($parts[1]->value)];
        }

        return null;
    }

    /** @param list<OutputPart> $parts */
    private function isBracketedNumericMessage(array $parts, string $catchVariable): bool
    {
        if (count($parts) < 2 || !self::isLiteral($parts[0]) || !$this->isExceptionMessage($parts[1], $catchVariable)) {
            return false;
        }

        if (!$this->markers->isBracketedNumeric($parts[0]->value)) {
            return false;
        }

        return $this->onlyNewlinesAfter($parts, 2);
    }

    /** @param list<OutputPart> $parts */
    private function isErrorNumberVarDump(array $parts, string $catchVariable): bool
    {
        if (2 !== count($parts) || !self::isLiteral($parts[0]) || !$this->isExceptionMessage($parts[1], $catchVariable)) {
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

        if (!self::isLiteral($parts[1]) || !str_ends_with($parts[1]->value, '=>')) {
            return false;
        }

        if (!$this->isExceptionClass($parts[2], $catchVariable)) {
            return false;
        }

        if (!self::isLiteral($parts[3]) || ': ' !== $parts[3]->value) {
            return false;
        }

        if (!$this->isExceptionMessage($parts[4], $catchVariable)) {
            return false;
        }

        return $this->onlyNewlinesAfter($parts, 5);
    }

    private function isExceptionClass(OutputPart $part, string $catchVariable): bool
    {
        return OutputPartKind::ExceptionClass === $part->kind && $part->variable === $catchVariable;
    }

    private function isExceptionMessage(OutputPart $part, string $catchVariable): bool
    {
        return OutputPartKind::ExceptionMessage === $part->kind && $part->variable === $catchVariable;
    }

    /** @param list<OutputPart> $parts */
    private function onlyNewlinesAfter(array $parts, int $offset): bool
    {
        for ($i = $offset; $i < count($parts); $i++) {
            if (OutputPartKind::Newline !== $parts[$i]->kind) {
                return false;
            }
        }

        return true;
    }

    private static function isLiteral(OutputPart $part): bool
    {
        return OutputPartKind::Literal === $part->kind;
    }

    private function quote(string $value): string
    {
        return '\'' . str_replace(['\\', '\''], ['\\\\', '\\\''], $value) . '\'';
    }
}
