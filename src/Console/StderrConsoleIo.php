<?php

declare(strict_types=1);

namespace InternalsCS\Console;

final readonly class StderrConsoleIo implements ConsoleIo
{
    public function __construct(
        private ConsoleIo $io,
    ) {}

    public function out(string $message): void
    {
        $this->io->err($message);
    }

    public function err(string $message): void
    {
        $this->io->err($message);
    }
}
