<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputParts;

use function str_replace;

final readonly class CanonicalStatementBuilder
{
    public function build(string $variable, OutputParts $parts, string $prefix = ''): string
    {
        $statement = 'echo ';

        if ('' !== $prefix) {
            $statement .= '\'' . $this->quote($prefix . ': ') . '\', ';
        }

        $statement .= '$' . $variable . '::class, \': \', $' . $variable . '->getMessage()';

        if ($parts->has(OutputPartKind::ExceptionFile)) {
            $statement .= ', \' in \', $' . $variable . '->getFile()';
        }

        if ($parts->has(OutputPartKind::ExceptionLine)) {
            $statement .= ', \' on line \', $' . $variable . '->getLine()';
        }

        return $statement . ', \\PHP_EOL;';
    }

    private function quote(string $value): string
    {
        return str_replace(['\\', '\''], ['\\\\', '\\\''], $value);
    }
}
