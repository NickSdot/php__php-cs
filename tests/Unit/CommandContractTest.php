<?php

declare(strict_types=1);

namespace Tests\Unit;

use InternalsCS\Console\Application;
use InternalsCS\Console\ConsoleIo;
use PHPUnit\Framework\TestCase;

final class CommandContractTest extends TestCase
{
    public function testFixRequiresExplicitPhpSrcDirectory(): void
    {
        $io = new BufferConsoleIo();

        self::assertSame(2, new Application($io)->run(['php-cs.php', 'fix']));
        self::assertSame("--php-src-dir /path/to/php-src is required\n", $io->stderr);
    }

    public function testGenerateRequiresExplicitPhpSrcDirectory(): void
    {
        $io = new BufferConsoleIo();

        self::assertSame(2, new Application($io)->run(['php-cs.php', 'generate']));
        self::assertSame("--php-src-dir /path/to/php-src is required\n", $io->stderr);
    }

    public function testCommandHelpReturnsSuccess(): void
    {
        $io = new BufferConsoleIo();
        $app = new Application($io);

        self::assertSame(0, $app->run(['php-cs.php', 'fix', '--help']));
        self::assertSame(0, $app->run(['php-cs.php', 'generate', '--help']));
        self::assertStringContainsString('Usage: php bin/php-cs.php fix', $io->stdout);
        self::assertStringContainsString('Usage: php bin/php-cs.php generate', $io->stdout);
        self::assertStringContainsString('--force-php-binary-rebuild', $io->stdout);
    }
}

final class BufferConsoleIo implements ConsoleIo
{
    public string $stdout = '';

    public string $stderr = '';

    public function out(string $message): void
    {
        $this->stdout .= $message;
    }

    public function err(string $message): void
    {
        $this->stderr .= $message;
    }
}
