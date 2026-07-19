<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Analysis;

use function preg_match;

final readonly class MarkerPrefixPolicy
{
    public function isBracketedNumeric(string $literal): bool
    {
        return 1 === preg_match('/^\[\d+\]\s+$/', $literal);
    }

    public function isErrorNumber(string $literal): bool
    {
        return 1 === preg_match('/^ERROR \d+$/', $literal);
    }

    public function isVariableMarkerSeparator(string $literal): bool
    {
        return '=>' === $literal;
    }

    public function isMarkerLiteral(string $literal): bool
    {
        return $this->isBracketedNumeric($literal)
            || $this->isErrorNumber($literal)
            || $this->isVariableMarkerSeparator($literal);
    }
}
