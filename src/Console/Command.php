<?php

declare(strict_types=1);

namespace InternalsCS\Console;

interface Command
{
    /** @param list<string> $args */
    public function run(string $script, array $args, ConsoleIo $io): int;
}
