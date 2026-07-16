<?php

declare(strict_types=1);

namespace InternalsCS\Console;

use function fwrite;

final class StreamConsoleIo implements ConsoleIo
{
    public function out(string $message): void
    {
        echo $message;
    }

    public function err(string $message): void
    {
        fwrite(STDERR, $message);
    }
}
