<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputParts;

use function implode;
use function str_replace;

final readonly class CanonicalStatementBuilder
{
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

        if ($parts->has(OutputPartKind::ExceptionCode)) {
            $segments[] = $this->literalSegment(': ');
            $segments[] = '$' . $variable . '->getCode()';
            $segments[] = $this->literalSegment(': ');
        } else {
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

        $segments[] = '\\PHP_EOL';

        return 'echo ' . implode(', ', $segments) . ';';
    }

    public function literalSegment(string $value): string
    {
        return '\'' . $this->quote($value) . '\'';
    }

    public function variableSegment(string $variable): string
    {
        return '$' . $variable;
    }

    private function quote(string $value): string
    {
        return str_replace(['\\', '\''], ['\\\\', '\\\''], $value);
    }
}
