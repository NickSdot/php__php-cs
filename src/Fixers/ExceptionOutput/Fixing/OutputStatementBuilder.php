<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Fixing;

use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputPart;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputParts;

use function count;
use function implode;
use function mb_rtrim;
use function mb_strlen;
use function mb_substr;
use function preg_match;
use function str_ends_with;
use function str_replace;
use function str_starts_with;

final readonly class OutputStatementBuilder
{
    public const string PHP_EOL_SOURCE = 'PHP_EOL';

    public function build(string $variable, OutputParts $parts, string $prefix = '', ?string $newlineSource = null): string
    {
        $segments = [];

        if ('' !== $prefix) {
            $segments[] = $this->literalSegment($prefix . ': ');
        }

        return $this->buildWithPrefixSegments($variable, $parts, $segments, $newlineSource);
    }

    /** @param list<string> $prefixSegments */
    public function buildWithPrefixSegments(string $variable, OutputParts $parts, array $prefixSegments, ?string $newlineSource = null): string
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

        $segments[] = $newlineSource ?? $this->trailingNewlineSource($parts);

        return 'echo ' . implode(', ', $segments) . ';';
    }

    public function firstNewlineSource(OutputParts $parts): string
    {
        foreach ($parts->parts as $part) {
            if (OutputPartKind::Newline === $part->kind) {
                return $this->newlinePartSource($part);
            }

            if (OutputPartKind::Literal === $part->kind) {
                if (null !== $newlineSource = $this->leadingLiteralNewlineSource($part->value)) {
                    return $newlineSource;
                }
            }
        }

        return self::PHP_EOL_SOURCE;
    }

    public function trailingNewlineSource(OutputParts $parts): string
    {
        for ($i = count($parts->parts) - 1; $i >= 0; $i--) {
            $part = $parts->parts[$i];

            if (OutputPartKind::Newline === $part->kind) {
                return $this->newlinePartSource($part);
            }

            if (OutputPartKind::Literal === $part->kind) {
                if (null !== $newlineSource = $this->trailingLiteralNewlineSource($part->value)) {
                    return $newlineSource;
                }
            }
        }

        if ('var_dump' === $parts->shape || str_starts_with($parts->shape, 'var_dump:')) {
            return '"\n"';
        }

        return self::PHP_EOL_SOURCE;
    }

    public function appendNewlineToEcho(string $source, string $newlineSource = self::PHP_EOL_SOURCE): ?string
    {
        $trimmed = mb_rtrim($source);

        if (!str_ends_with($trimmed, ';')) {
            return null;
        }

        $tail = mb_substr($source, mb_strlen($trimmed, '8bit'), null, '8bit');

        return mb_substr($trimmed, 0, -1, '8bit') . ', ' . $newlineSource . ';' . $tail;
    }

    public function buildSameStatementTrace(string $variable, string $prefix, string $newlineSource = self::PHP_EOL_SOURCE): string
    {
        return 'echo '
            . $this->literalSegment($prefix)
            . ', $'
            . $variable
            . '::class . \': \' . $'
            . $variable
            . '->getMessage(), '
            . $newlineSource
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

    private function newlinePartSource(OutputPart $part): string
    {
        return $part->newlineSource ?? self::PHP_EOL_SOURCE;
    }

    private function leadingLiteralNewlineSource(string $value): ?string
    {
        return match (true) {
            str_starts_with($value, "\r\n") => '"\r\n"',
            str_starts_with($value, "\n") => '"\n"',
            str_starts_with($value, "\r") => '"\r"',
            default => null,
        };
    }

    private function trailingLiteralNewlineSource(string $value): ?string
    {
        return match (true) {
            str_ends_with($value, "\r\n") => '"\r\n"',
            str_ends_with($value, "\n") => '"\n"',
            str_ends_with($value, "\r") => '"\r"',
            default => null,
        };
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
        return str_replace(
            ["\\", "\n", "\r", "\t", '"', '$'],
            ["\\\\", '\n', '\r', '\t', '\\"', '\\$'],
            $value,
        );
    }
}
