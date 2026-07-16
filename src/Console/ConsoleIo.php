<?php

declare(strict_types=1);

namespace InternalsCS\Console;

interface ConsoleIo
{
    public function out(string $message): void;

    public function err(string $message): void;
}
