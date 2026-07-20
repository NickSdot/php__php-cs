<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Fixing;

use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputPart;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputParts;

use function implode;
use function mb_rtrim;
use function mb_strlen;
use function mb_substr;
use function preg_match;
use function str_ends_with;
use function str_replace;

final readonly class OutputStatementBuilder
{
    public const string NEWLINE_SEGMENT = 'PHP_EOL';

    public function build(string $variable, OutputParts $parts, string $prefix = ''): string
    {
        $segments = [];

        if ('' !== $prefix) {
            $segments[] = $this->literalSegment($prefix . ': ');
        }

        return $this->buildWithPrefixSegments($variable, $parts, $segments);
    }

    /** @param list<string> $prefixSegments */
    public function buildWithPrefixSegments(string $variable, OutputParts $parts, array $prefixSegments): string
    {
        $segments = [
            ...$prefixSegments,
            '$' . $variable . '::class',
        ];

        $segments[] = $this->literalSegment(': ');

        if ($parts->has(OutputPartKind::ExceptionCode)) {
            $segments[] = '$' . $variable . '->getCode()';
            $segments[] = $this->literalSegment(': ');
        }

        $segments[] = '$' . $variable . '->getMessage()';

        if ($parts->has(OutputPartKind::ExceptionFile)) {
            $segments[] = $this->literalSegment(' in ');
            $segments[] = '$' . $variable . '->getFile()';
        }

        if ($parts->has(OutputPartKind::ExceptionLine)) {
            $segments[] = $this->literalSegment(' on line ');
            $segments[] = '$' . $variable . '->getLine()';
        }

        $segments[] = self::NEWLINE_SEGMENT;

        return 'echo ' . implode(', ', $segments) . ';';
    }

    public function appendNewlineSegmentToEcho(string $source): ?string
    {
        $trimmed = mb_rtrim($source);

        if (!str_ends_with($trimmed, ';')) {
            return null;
        }

        $tail = mb_substr($source, mb_strlen($trimmed, '8bit'), null, '8bit');

        return mb_substr($trimmed, 0, -1, '8bit') . ', ' . self::NEWLINE_SEGMENT . ';' . $tail;
    }

    public function buildSameStatementTrace(string $variable, string $prefix): string
    {
        return 'echo '
            . $this->literalSegment($prefix)
            . ', $'
            . $variable
            . '::class . \': \' . $'
            . $variable
            . '->getMessage(), '
            . self::NEWLINE_SEGMENT
            . ', $'
            . $variable
            . '->getTraceAsString();';
    }

    public function literalSegment(string $value): string
    {
        return '\'' . $this->quoteSingle($value) . '\'';
    }

    public function variableSegment(string $variable): string
    {
        return '$' . $variable;
    }

    /** @param list<OutputPart> $parts */
    public function interpolatedSegment(array $parts): ?string
    {
        $body = '';
        $hasVariable = false;

        foreach ($parts as $i => $part) {
            if (OutputPart::SOURCE_INTERPOLATED_STRING !== $part->source) {
                return null;
            }

            if (OutputPartKind::Literal === $part->kind) {
                $body .= $this->quoteDouble($part->value);
                continue;
            }

            if (OutputPartKind::OtherVariable !== $part->kind || null === $part->variable) {
                return null;
            }

            $body .= $this->interpolatedVariable($part->variable, $parts[$i + 1] ?? null);
            $hasVariable = true;
        }

        return $hasVariable ? '"' . $body . '"' : null;
    }

    private function interpolatedVariable(string $variable, ?OutputPart $next): string
    {
        if (null !== $next && OutputPartKind::Literal === $next->kind && 1 === preg_match('/^[A-Za-z0-9_]/', $next->value)) {
            return '{$' . $variable . '}';
        }

        return '$' . $variable;
    }

    private function quoteSingle(string $value): string
    {
        return str_replace(['\\', '\''], ['\\\\', '\\\''], $value);
    }

    private function quoteDouble(string $value): string
    {
        return str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value);
    }
}
