<?php

declare(strict_types=1);

namespace InternalsCS\Command;

final class CommandExit extends \RuntimeException
{
    public function __construct(
        public readonly int $exitCode,
    ) {
        parent::__construct();
    }
}
