<?php

declare(strict_types=1);

namespace Tests\Unit;

use InternalsCS\PhpSrcTestStyle\PhptTestRuntime;
use InternalsCS\PhpSrcTestStyle\PhptTestRuntimeResolver;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function chmod;
use function dirname;
use function file_put_contents;
use function getenv;
use function is_dir;
use function is_string;
use function mkdir;
use function putenv;
use function random_bytes;
use function sys_get_temp_dir;

final class PhptTestRuntimeResolverTest extends TestCase
{
    public function testUsesConfiguredPhpBinaryBeforePhpSrcBuild(): void
    {
        $root = $this->makeTempDir();
        $configured = $this->writeExecutable($root . '/configured-php');
        $this->writeExecutable($root . '/php-src/sapi/cli/php');

        $runtime = $this->withConfiguredPhpBinary($configured, function () use ($root): PhptTestRuntime {
            $runtime = new PhptTestRuntimeResolver($root)->resolve($root . '/php-src');

            return $runtime;
        });

        self::assertSame($configured, $runtime->phpBinary);
    }

    public function testUsesPhpSrcBuildByDefault(): void
    {
        $root = $this->makeTempDir();
        $php = $this->writeExecutable($root . '/php-src/sapi/cli/php');
        $cgi = $this->writeExecutable($root . '/php-src/sapi/cgi/php-cgi');

        $runtime = $this->withoutConfiguredPhpBinary(
            fn(): PhptTestRuntime => new PhptTestRuntimeResolver($root)->resolve($root . '/php-src'),
        );

        self::assertSame($php, $runtime->phpBinary);
        self::assertSame($cgi, $runtime->phpCgiBinary);
    }

    public function testUsesManagedRuntimeOnlyForManagedRuntimeSource(): void
    {
        $root = $this->makeTempDir();
        $php = $this->writeExecutable($root . '/var/php-test-runtime/php');
        $this->writeExecutable($root . '/var/php-test-runtime/php-cgi');
        mkdir($root . '/var/php-test-runtime/source', recursive: true);

        $runtime = $this->withoutConfiguredPhpBinary(
            fn(): PhptTestRuntime => new PhptTestRuntimeResolver($root)->resolve($root . '/var/php-test-runtime/source'),
        );

        self::assertSame($php, $runtime->phpBinary);
    }

    public function testRejectsMissingPhpBinary(): void
    {
        $root = $this->makeTempDir();
        mkdir($root . '/php-src');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No PHP test binary found');

        $this->withoutConfiguredPhpBinary(
            fn(): PhptTestRuntime => new PhptTestRuntimeResolver($root)->resolve($root . '/php-src'),
        );
    }

    private function makeTempDir(): string
    {
        $path = sys_get_temp_dir() . '/phpt-runtime-' . bin2hex(random_bytes(6));
        mkdir($path);

        return $path;
    }

    private function writeExecutable(string $path): string
    {
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, recursive: true);
        }

        file_put_contents($path, "#!/bin/sh\nexit 0\n");
        chmod($path, 0o755);

        return $path;
    }

    /** @param callable(): PhptTestRuntime $callback */
    private function withoutConfiguredPhpBinary(callable $callback): PhptTestRuntime
    {
        $previousEnv = getenv('INTERNALS_CS_TEST_PHP_EXECUTABLE');
        $previous = $_ENV['INTERNALS_CS_TEST_PHP_EXECUTABLE'] ?? null;
        putenv('INTERNALS_CS_TEST_PHP_EXECUTABLE');
        unset($_ENV['INTERNALS_CS_TEST_PHP_EXECUTABLE']);

        try {
            return $callback();
        } finally {
            if (is_string($previousEnv)) {
                putenv('INTERNALS_CS_TEST_PHP_EXECUTABLE=' . $previousEnv);
            } else {
                putenv('INTERNALS_CS_TEST_PHP_EXECUTABLE');
            }

            if (null !== $previous) {
                $_ENV['INTERNALS_CS_TEST_PHP_EXECUTABLE'] = $previous;
            }
        }
    }

    /** @param callable(): PhptTestRuntime $callback */
    private function withConfiguredPhpBinary(string $phpBinary, callable $callback): PhptTestRuntime
    {
        $previousEnv = getenv('INTERNALS_CS_TEST_PHP_EXECUTABLE');
        $previous = $_ENV['INTERNALS_CS_TEST_PHP_EXECUTABLE'] ?? null;
        putenv('INTERNALS_CS_TEST_PHP_EXECUTABLE=' . $phpBinary);
        $_ENV['INTERNALS_CS_TEST_PHP_EXECUTABLE'] = $phpBinary;

        try {
            return $callback();
        } finally {
            if (is_string($previousEnv)) {
                putenv('INTERNALS_CS_TEST_PHP_EXECUTABLE=' . $previousEnv);
            } else {
                putenv('INTERNALS_CS_TEST_PHP_EXECUTABLE');
            }

            if (null === $previous) {
                unset($_ENV['INTERNALS_CS_TEST_PHP_EXECUTABLE']);
            } else {
                $_ENV['INTERNALS_CS_TEST_PHP_EXECUTABLE'] = $previous;
            }
        }
    }
}
