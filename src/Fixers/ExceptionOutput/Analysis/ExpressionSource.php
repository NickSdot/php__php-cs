<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Analysis;

use PhpParser\Node;

use function is_int;
use function mb_substr;

final readonly class ExpressionSource
{
    public function __construct(
        private string $code,
        private int $offsetDelta,
    ) {}

    public function forNode(Node $node): ?string
    {
        $start = $node->getAttribute('startFilePos');
        $end = $node->getAttribute('endFilePos');

        if (!is_int($start) || !is_int($end)) {
            return null;
        }

        $start -= $this->offsetDelta;
        $end -= $this->offsetDelta;

        if ($start < 0 || $end < $start) {
            return null;
        }

        return mb_substr($this->code, $start, $end - $start + 1, '8bit');
    }
}
