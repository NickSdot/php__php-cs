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
        $this->io->out(<<<USAGE
            Usage:
              php bin/$script <command> [options]

            Commands:
              fix       Apply or check PHPT fixes in a php-src checkout.
              generate  Generate or refresh fixer fixtures.

            Run `php bin/$script <command> --help` for command options.

            USAGE);
    }
}
