<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Analysis;

use InternalsCS\Support\Whitespace;

use function count;
use function mb_rtrim;
use function preg_match;
use function preg_quote;

final readonly class OutputExpectationAnalyzer
{
    public function analyze(OutputParts $output, ?string $expectedOutput): OutputExpectation
    {
        if (null === $expectedOutput) {
            return new OutputExpectation();
        }

        [$pattern, $captures] = $this->pattern($output);

        if (null === $pattern || 1 !== preg_match($pattern, Whitespace::normalizeLineEndingsToLf($expectedOutput), $matches)) {
            return new OutputExpectation();
        }

        $values = [];

        foreach ($captures as $offset => $kind) {
            $values[$kind->value] ??= $matches[$offset] ?? '';
        }

        return new OutputExpectation($values);
    }

    /** @return array{string|null, array<int, OutputPartKind>} */
    private function pattern(OutputParts $output): array
    {
        $parts = $output->parts;

        if ([] === $parts || !$output->has(OutputPartKind::ExceptionMessage)) {
            return [null, []];
        }

        $pattern = '';
        $captures = [];
        $captureOffset = 1;
        $hasAnchor = false;
        $lastOffset = count($parts) - 1;

        foreach ($parts as $offset => $part) {

            if (OutputPartKind::Literal === $part->kind) {

                $literal = Whitespace::normalizeLineEndingsToLf($part->value);

                if ($offset === $lastOffset) {
                    $literal = mb_rtrim($literal, "\n");
                }

                if ('' !== $literal) {
                    $hasAnchor = true;
                    $pattern .= preg_quote($literal, '~');
                }

                continue;
            }

            if (OutputPartKind::Newline === $part->kind && $offset === $lastOffset) {
                continue;
            }

            $dynamicPattern = $this->dynamicPattern($part->kind);

            if (null === $dynamicPattern) {
                return [null, []];
            }

            if (OutputPartKind::ExceptionClass === $part->kind) {
                $hasAnchor = true;
            }

            if ($this->observes($part->kind)) {
                $pattern .= '(' . $dynamicPattern . ')';
                $captures[$captureOffset++] = $part->kind;
                continue;
            }

            $pattern .= '(?:' . $dynamicPattern . ')';
        }

        if (!$hasAnchor) {
            return [null, []];
        }

        return ['~(?:^|\n)' . $pattern . '(?=\n|$)~', $captures];
    }

    private function dynamicPattern(OutputPartKind $kind): ?string
    {
        return match ($kind) {
            OutputPartKind::ExceptionClass => '[A-Za-z_\\\\][A-Za-z0-9_\\\\]*',
            OutputPartKind::ExceptionLine => '\\d+',
            OutputPartKind::ExceptionMessage,
            OutputPartKind::ExceptionCode,
            OutputPartKind::ExceptionFile,
            OutputPartKind::OtherVariable,
            OutputPartKind::OtherExpression => '[^\n]*?',
            default => null,
        };
    }

    private function observes(OutputPartKind $kind): bool
    {
        return match ($kind) {
            OutputPartKind::ExceptionClass,
            OutputPartKind::ExceptionMessage,
            OutputPartKind::ExceptionCode,
            OutputPartKind::ExceptionFile,
            OutputPartKind::ExceptionLine => true,
            default => false,
        };
    }
}
