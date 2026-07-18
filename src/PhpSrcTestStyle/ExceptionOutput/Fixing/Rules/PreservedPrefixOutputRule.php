<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPart;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalStatementBuilder;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\OutputPartMatcher;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteContext;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteRule;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;

use function count;
use function in_array;

final readonly class PreservedPrefixOutputRule implements RewriteRule
{
    /** @var list<string> */
    private const array LITERAL_PREFIXES = [
        'Wrong exception type thrown: ',
        'saveXml: ',
        'innerHTML: ',
        'fputcsv: ',
        'next: ',
        'next (READ_AHEAD): ',
        'bool: ',
        'string: ',
    ];

    public function __construct(
        private CanonicalStatementBuilder $builder = new CanonicalStatementBuilder(),
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
        if ($this->isLiteralPrefixMessage($parts, $catchVariable)) {
            return [$this->builder->literalSegment($parts[0]->value)];
        }

        if ($this->isLiteralPrefixClassMessage($parts, $catchVariable)) {
            return [$this->builder->literalSegment($parts[0]->value)];
        }

        if ($this->isVariablePrefixMessage($parts, $catchVariable)) {
            return [$this->builder->variableSegment((string) $parts[0]->variable), $this->builder->literalSegment($parts[1]->value)];
        }

        return null;
    }

    /** @param list<OutputPart> $parts */
    private function isLiteralPrefixMessage(array $parts, string $catchVariable): bool
    {
        if (count($parts) < 2 || !$this->isAllowedLiteral($parts[0]) || !$this->parts->isExceptionMessage($parts[1], $catchVariable)) {
            return false;
        }

        return $this->parts->onlyNewlinesAfter($parts, 2);
    }

    /** @param list<OutputPart> $parts */
    private function isLiteralPrefixClassMessage(array $parts, string $catchVariable): bool
    {
        if (count($parts) < 4 || !$this->isAllowedLiteral($parts[0]) || !$this->parts->isExceptionClass($parts[1], $catchVariable)) {
            return false;
        }

        if (!$this->parts->isLiteral($parts[2]) || !in_array($parts[2]->value, [': ', ' : '], true)) {
            return false;
        }

        if (!$this->parts->isExceptionMessage($parts[3], $catchVariable)) {
            return false;
        }

        return $this->parts->onlyNewlinesAfter($parts, 4);
    }

    /** @param list<OutputPart> $parts */
    private function isVariablePrefixMessage(array $parts, string $catchVariable): bool
    {
        if (count($parts) < 3 || OutputPartKind::OtherVariable !== $parts[0]->kind || null === $parts[0]->variable) {
            return false;
        }

        if (!$this->parts->isLiteral($parts[1], ': ')) {
            return false;
        }

        if (!$this->parts->isExceptionMessage($parts[2], $catchVariable)) {
            return false;
        }

        return $this->parts->onlyNewlinesAfter($parts, 3);
    }

    private function isAllowedLiteral(OutputPart $part): bool
    {
        return $this->parts->isLiteral($part) && in_array($part->value, self::LITERAL_PREFIXES, true);
    }
}
