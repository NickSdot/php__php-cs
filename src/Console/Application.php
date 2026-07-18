<?php

declare(strict_types=1);

namespace InternalsCS\Console;

use InternalsCS\Command\FixCommand;
use InternalsCS\Command\GenerateCommand;

use function array_slice;
use function basename;

final readonly class Application
{
    public function __construct(
        private ConsoleIo $io,
    ) {}

    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        $script = basename($argv[0] ?? 'php-src-cs.php');
        $commandName = $argv[1] ?? null;

        if (null === $commandName) {
            $this->usage($script);
            return 2;
        }

        if ('--help' === $commandName || '-h' === $commandName) {
            $this->usage($script);
            return 0;
        }

        $command = $this->command($commandName);

        if (null === $command) {
            $this->io->err('Unknown command: ' . $commandName . "\n");
            $this->usage($script);
            return 2;
        }

        return $command->run(
            script: $script . ' ' . $commandName,
            args: array_slice($argv, 2),
            io: $this->io,
        );
    }

    private function command(string $name): ?Command
    {
        return match ($name) {
            'fix' => new FixCommand(),
            'generate' => new GenerateCommand(),
            default => null,
        };
    }

    private function usage(string $script): void
    {
        $this->io->out("Usage: php bin/$script <command> [options]\n");
        $this->io->out("\n");
        $this->io->out("Commands:\n");
        $this->io->out("  fix       Apply or check exception-message output style in php-src PHPT files\n");
        $this->io->out("  generate  Run fixture/data generators\n");
        $this->io->out("\n");
        $this->io->out("Run php bin/$script <command> --help for command options.\n");
    }
}
