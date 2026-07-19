<?php

declare(strict_types=1);

namespace Tests\Unit;

use InternalsCS\Console\Application;
use InternalsCS\Console\ConsoleIo;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_put_contents;
use function getenv;
use function is_string;
use function mkdir;
use function putenv;
use function random_bytes;
use function sys_get_temp_dir;

final class CommandContractTest extends TestCase
{
    public function testFixRequiresExplicitPhpSrcDirectory(): void
    {
        $io = new BufferConsoleIo();

        self::assertSame(2, new Application($io)->run(['php-src-cs.php', 'fix']));
        self::assertSame("--php-src-dir /path/to/php-src is required\n", $io->stderr);
    }

    public function testGenerateRequiresExplicitPhpSrcDirectory(): void
    {
        $io = new BufferConsoleIo();

        self::assertSame(2, new Application($io)->run(['php-src-cs.php', 'generate']));
        self::assertSame("--php-src-dir /path/to/php-src is required\n", $io->stderr);
    }

    public function testCommandHelpReturnsSuccess(): void
    {
        $fixIo = new BufferConsoleIo();
        $generateIo = new BufferConsoleIo();
        $generateTargetIo = new BufferConsoleIo();

        self::assertSame(0, new Application($fixIo)->run(['php-src-cs.php', 'fix', '--help']));
        self::assertSame(0, new Application($generateIo)->run(['php-src-cs.php', 'generate', '--help']));
        self::assertSame(0, new Application($generateTargetIo)->run(['php-src-cs.php', 'generate', 'exception-output', '--help']));

        self::assertStringContainsString('Usage: php bin/php-src-cs.php fix', $fixIo->stdout);
        self::assertStringNotContainsString('--force-php-binary-rebuild', $fixIo->stdout);
        self::assertStringContainsString('Usage: php bin/php-src-cs.php generate', $generateIo->stdout);
        self::assertStringContainsString('--force-php-binary-rebuild', $generateTargetIo->stdout);
    }

    public function testFixWriteRunWithSkippedCandidateReturnsSuccess(): void
    {
        $root = $this->rootWithRunTests(<<<'PHP'
            <?php
            $target = $argv[\array_key_last($argv)];
            $contents = \file_get_contents($target);

            if (\str_ends_with($contents, "\n")) {
                echo "FAIL $target\n";
                exit(1);
            }

            echo "PASS $target\n";
            PHP);
        $path = $root . '/unsafe.phpt';
        $io = new BufferConsoleIo();

        file_put_contents($path, "--TEST--\nunsafe final newline\n--FILE--\n<?php\n--EXPECT--");

        $this->withConfiguredPhpBinary(function () use ($io, $root, $path): void {
            $exitCode = new Application($io)->run([
                'php-src-cs.php',
                'fix',
                '--php-src-dir',
                $root,
                '--fixer',
                'final-newline',
                $path,
            ]);

            self::assertSame(0, $exitCode, $io->stderr);
        });

        self::assertStringContainsString('unsafe.phpt: final-newline', $io->stdout);
        self::assertStringContainsString('skipped:', $io->stdout);
        self::assertStringContainsString('Report: var/fix-runs/', $io->stdout);
    }

    public function testFixWriteRunRequiresExistingPhpTestBinary(): void
    {
        $root = $this->rootWithRunTests("<?php\n");
        $path = $root . '/test.phpt';
        $io = new BufferConsoleIo();

        file_put_contents($path, "--TEST--\nmissing runtime\n--FILE--\n<?php\n--EXPECT--");

        $this->withoutConfiguredPhpBinary(function () use ($io, $root, $path): void {
            self::assertSame(2, new Application($io)->run([
                'php-src-cs.php',
                'fix',
                '--php-src-dir',
                $root,
                '--fixer',
                'final-newline',
                $path,
            ]));
        });

        self::assertStringContainsString('No PHP test binary found.', $io->stderr);
        self::assertStringNotContainsString('Report: var/fix-runs/', $io->stdout);
    }

    private function rootWithRunTests(string $runTests): string
    {
        $root = sys_get_temp_dir() . '/command-contract-' . bin2hex(random_bytes(6));
        mkdir($root);
        file_put_contents($root . '/run-tests.php', $runTests);

        return $root;
    }

    private function withConfiguredPhpBinary(callable $callback): void
    {
        $previousEnv = getenv('INTERNALS_CS_TEST_PHP_EXECUTABLE');
        $previous = $_ENV['INTERNALS_CS_TEST_PHP_EXECUTABLE'] ?? null;
        putenv('INTERNALS_CS_TEST_PHP_EXECUTABLE=' . PHP_BINARY);
        $_ENV['INTERNALS_CS_TEST_PHP_EXECUTABLE'] = PHP_BINARY;

        try {
            $callback();
        } finally {
            if (is_string($previousEnv)) {
                putenv('INTERNALS_CS_TEST_PHP_EXECUTABLE=' . $previousEnv);
            } else {
                putenv('INTERNALS_CS_TEST_PHP_EXECUTABLE');
            }

            if (null === $previous) {
                unset($_ENV['INTERNALS_CS_TEST_PHP_EXECUTABLE']);
                return;
            }

            $_ENV['INTERNALS_CS_TEST_PHP_EXECUTABLE'] = $previous;
        }
    }

    private function withoutConfiguredPhpBinary(callable $callback): void
    {
        $previousEnv = getenv('INTERNALS_CS_TEST_PHP_EXECUTABLE');
        $previous = $_ENV['INTERNALS_CS_TEST_PHP_EXECUTABLE'] ?? null;
        putenv('INTERNALS_CS_TEST_PHP_EXECUTABLE');
        unset($_ENV['INTERNALS_CS_TEST_PHP_EXECUTABLE']);

        try {
            $callback();
        } finally {
            if (is_string($previousEnv)) {
                putenv('INTERNALS_CS_TEST_PHP_EXECUTABLE=' . $previousEnv);
            }

            if (null !== $previous) {
                $_ENV['INTERNALS_CS_TEST_PHP_EXECUTABLE'] = $previous;
            }
        }
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
