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
            $segments[] = '\'' . $this->quote($prefix . ': ') . '\'';
        }

        return $this->buildWithPrefixSegments($variable, $parts, $segments);
    }

    /** @param list<string> $prefixSegments */
    public function buildWithPrefixSegments(string $variable, OutputParts $parts, array $prefixSegments): string
    {
        $segments = [
            ...$prefixSegments,
            '$' . $variable . '::class',
            '\': \'',
            '$' . $variable . '->getMessage()',
        ];

        if ($parts->has(OutputPartKind::ExceptionFile)) {
            $segments[] = '\' in \'';
            $segments[] = '$' . $variable . '->getFile()';
        }

        if ($parts->has(OutputPartKind::ExceptionLine)) {
            $segments[] = '\' on line \'';
            $segments[] = '$' . $variable . '->getLine()';
        }

        $segments[] = '\\PHP_EOL';

        return 'echo ' . implode(', ', $segments) . ';';
    }

    private function quote(string $value): string
    {
        return str_replace(['\\', '\''], ['\\\\', '\\\''], $value);
    }
}
