<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputParts;

final readonly class CanonicalStatementBuilder
{
    public function build(string $variable, OutputParts $parts): string
    {
        $statement = 'echo $' . $variable . '::class, \': \', $' . $variable . '->getMessage()';

        if ($parts->has(OutputPartKind::ExceptionFile)) {
            $statement .= ', \' in \', $' . $variable . '->getFile()';
        }

        if ($parts->has(OutputPartKind::ExceptionLine)) {
            $statement .= ', \' on line \', $' . $variable . '->getLine()';
        }

        return $statement . ', \\PHP_EOL;';
    }
}
