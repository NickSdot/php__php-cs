<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPart;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\OutputPartMatcher;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\OutputStatementBuilder;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteContext;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteRule;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;

use function count;
use function implode;

final readonly class MixedVarDumpMessageOutputRule implements RewriteRule
{
    public function __construct(
        private OutputStatementBuilder $builder = new OutputStatementBuilder(),
        private OutputPartMatcher $parts = new OutputPartMatcher(),
    ) {}

    public function rewrite(RewriteContext $context): ?RewriteResult
    {
        $statement = $context->statement;
        $remainingVariables = $this->remainingVariables($statement->parts->parts, $context->catchVariable);

        if (null === $remainingVariables || 'var_dump' !== $statement->parts->shape) {
            return null;
        }

        return new RewriteResult(new TextEdit(
            startOffset: $statement->startOffset,
            endOffset: $statement->endOffset,
            line: $statement->line,
            replacement: $this->builder->build($context->catchVariable, $statement->parts)
                . "\n"
                . $statement->indent
                . 'var_dump(' . implode(', ', $remainingVariables) . ');',
        ));
    }

    /**
     * @param list<OutputPart> $parts
     * @return list<string>|null
     */
    private function remainingVariables(array $parts, string $catchVariable): ?array
    {
        if (count($parts) < 2 || !$this->parts->isExceptionMessage($parts[0], $catchVariable)) {
            return null;
        }

        $variables = [];

        for ($i = 1; $i < count($parts); $i++) {
            if (OutputPartKind::OtherVariable !== $parts[$i]->kind || null === $parts[$i]->variable) {
                return null;
            }

            $variables[] = '$' . $parts[$i]->variable;
        }

        return $variables;
    }
}
