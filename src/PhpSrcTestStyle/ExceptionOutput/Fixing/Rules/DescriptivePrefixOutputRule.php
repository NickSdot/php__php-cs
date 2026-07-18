<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\ClassificationSafety;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputFamily;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPart;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalRewriteSafety;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalStatementBuilder;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\OutputPartMatcher;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteContext;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\RewriteRule;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;

use function count;
use function mb_strlen;
use function mb_strtolower;
use function mb_substr;
use function mb_trim;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function str_contains;
use function str_ends_with;
use function usort;

final readonly class DescriptivePrefixOutputRule implements RewriteRule
{
    public function __construct(
        private CanonicalRewriteSafety $safety = new CanonicalRewriteSafety(),
        private CanonicalStatementBuilder $builder = new CanonicalStatementBuilder(),
        private CatchTypeLabels $catchTypeLabels = new CatchTypeLabels(),
        private OutputPartMatcher $partMatcher = new OutputPartMatcher(),
    ) {}

    public function rewrite(RewriteContext $context): ?RewriteResult
    {
        $statement = $context->statement;
        $prefix = $this->prefix($statement->parts->parts, $context->catchTypes, $context->catchVariable);

        if (null === $prefix) {
            return null;
        }

        if (OutputFamily::MessageOnly !== $statement->classification->family) {
            return null;
        }

        if (ClassificationSafety::DescriptiveContext !== $statement->classification->safety) {
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
            replacement: $this->builder->build($context->catchVariable, $statement->parts, $prefix),
        ));
    }

    /**
     * @param list<OutputPart> $parts
     * @param list<string> $catchTypes
     */
    private function prefix(array $parts, array $catchTypes, string $catchVariable): ?string
    {
        if (count($parts) < 2) {
            return null;
        }

        if (!$this->partMatcher->isLiteral($parts[0])) {
            return null;
        }

        if (!$this->partMatcher->isExceptionMessage($parts[1], $catchVariable)) {
            return null;
        }

        if (!$this->partMatcher->onlyNewlinesAfter($parts, 2)) {
            return null;
        }

        $original = $this->trimLabel($parts[0]->value);
        $prefix = $this->trimLabel($this->removeCatchType($original, $catchTypes));

        if (!$this->hasSafeDiagnosticSignal($original, $prefix)) {
            return null;
        }

        return '' === $prefix ? null : $prefix;
    }

    /** @param list<string> $catchTypes */
    private function removeCatchType(string $prefix, array $catchTypes): string
    {
        $names = $this->catchTypeLabels->names($catchTypes);
        usort($names, fn(string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($names as $name) {
            $prefix = (string) preg_replace(
                '/(?<![A-Za-z0-9_\\\\])\\\\?' . preg_quote($name, '/') . '(?![A-Za-z0-9_])/i',
                '',
                $prefix,
            );
        }

        return $prefix;
    }

    private function hasSafeDiagnosticSignal(string $original, string $prefix): bool
    {
        $original = mb_strtolower($original);
        $prefix = mb_strtolower($prefix);

        if ($original !== $prefix && str_contains($original, 'unexpected')) {
            return true;
        }

        return str_ends_with($prefix, ' failed')
            || str_ends_with($prefix, ' rejected')
            || str_ends_with($prefix, ' should be masked');
    }

    private function trimLabel(string $label): string
    {
        $label = mb_trim($label);

        while (1 === preg_match('/[:\s]$/', $label)) {
            $label = mb_trim(mb_substr($label, 0, -1));
        }

        return $label;
    }
}
