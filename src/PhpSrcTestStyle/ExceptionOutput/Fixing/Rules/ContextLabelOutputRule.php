<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPart;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalRewriteSafety;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalStatementBuilder;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\OutputPartMatcher;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteContext;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteRule;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;

use function mb_strlen;
use function mb_strtolower;
use function mb_substr;
use function mb_trim;
use function str_starts_with;

final readonly class ContextLabelOutputRule implements RewriteRule
{
    public function __construct(
        private CanonicalRewriteSafety $safety = new CanonicalRewriteSafety(),
        private CanonicalStatementBuilder $builder = new CanonicalStatementBuilder(),
        private OutputPartMatcher $parts = new OutputPartMatcher(),
    ) {}

    public function rewrite(RewriteContext $context): ?RewriteResult
    {
        $statement = $context->statement;
        $prefix = $this->canonicalPrefix($statement->parts->parts[0] ?? null);

        if (null === $prefix) {
            return null;
        }

        if ($statement->parts->hasUnknown()) {
            return null;
        }

        if (null === $this->parts->exceptionMessageOffset($statement->parts->parts, $context->catchVariable)) {
            return null;
        }

        if (!$this->safety->usesOnlyVariable($statement->parts, $context->catchVariable)) {
            return null;
        }

        return new RewriteResult(new TextEdit(
            startOffset: $statement->startOffset,
            endOffset: $statement->endOffset,
            line: $statement->line,
            replacement: $this->builder->build($context->catchVariable, $statement->parts, $prefix),
        ));
    }

    private function canonicalPrefix(?OutputPart $part): ?string
    {
        if (!$part instanceof OutputPart || !$this->parts->isLiteral($part)) {
            return null;
        }

        $label = $this->normalizedLabel($part->value);

        if (str_starts_with($label, 'expected exception for ')) {
            return $this->suffix($label, 'expected exception for ');
        }

        if (str_starts_with($label, 'exception thrown for ')) {
            return $this->suffix($label, 'exception thrown for ');
        }

        if ('unexpected exception' === $label) {
            return 'unexpected';
        }

        return null;
    }

    private function normalizedLabel(string $literal): string
    {
        return mb_strtolower(mb_trim(mb_trim($literal), " *:-[]().\"'!"));
    }

    private function suffix(string $label, string $prefix): ?string
    {
        $suffix = mb_trim(mb_substr($label, mb_strlen($prefix)));

        return '' === $suffix ? null : $suffix;
    }
}
