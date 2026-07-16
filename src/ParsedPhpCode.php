<?php

declare(strict_types=1);

namespace InternalsCS;

use PhpParser\Node\Stmt;

final readonly class ParsedPhpCode
{
    /** @param list<Stmt> $statements */
    public function __construct(
        public array $statements,
        public int $offsetDelta,
    ) {}
}
