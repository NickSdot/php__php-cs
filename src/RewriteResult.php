<?php

declare(strict_types=1);

namespace InternalsCS;

final readonly class RewriteResult
{
    public function __construct(
        public TextEdit $edit,
        public int $consumedStatements = 1,
    ) {}
}
