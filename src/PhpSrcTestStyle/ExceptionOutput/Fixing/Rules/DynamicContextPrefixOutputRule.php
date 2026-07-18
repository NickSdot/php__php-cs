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

final readonly class DynamicContextPrefixOutputRule implements RewriteRule
{
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
        $messageOffset = $this->parts->exceptionMessageOffset($parts, $catchVariable);

        if (null === $messageOffset || !$this->parts->onlyNewlinesAfter($parts, $messageOffset + 1)) {
            return null;
        }

        $prefixParts = [];

        for ($i = 0; $i < $messageOffset; $i++) {
            $prefixParts[] = $parts[$i];
        }

        if (!$this->isParenthesizedTwoVariableLabel($prefixParts)) {
            return null;
        }

        $segments = [];

        foreach ($prefixParts as $part) {
            $segments[] = OutputPartKind::OtherVariable === $part->kind
                ? $this->builder->variableSegment((string) $part->variable)
                : $this->builder->literalSegment($part->value);
        }

        return $segments;
    }

    /** @param list<OutputPart> $parts */
    private function isParenthesizedTwoVariableLabel(array $parts): bool
    {
        return 5 === count($parts)
            && $this->parts->isLiteral($parts[0], '(')
            && OutputPartKind::OtherVariable === $parts[1]->kind
            && $this->parts->isLiteral($parts[2], ', "')
            && OutputPartKind::OtherVariable === $parts[3]->kind
            && $this->parts->isLiteral($parts[4], '"): ');
    }
}
