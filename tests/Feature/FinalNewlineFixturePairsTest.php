<?php

declare(strict_types=1);

namespace Tests\Feature;

use InternalsCS\Console\Application;
use InternalsCS\Console\ConsoleIo;
use InternalsCS\Support\UnifiedDiff;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function basename;
use function bin2hex;
use function copy;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function mkdir;
use function random_bytes;
use function sort;
use function sys_get_temp_dir;

final class FinalNewlineFixturePairsTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function fixtureDirectories(): iterable
    {
        $dirs = glob(self::fixturesDir() . '/*', GLOB_ONLYDIR);

        if (false === $dirs) {
            return;
        }

        sort($dirs);

        foreach ($dirs as $dir) {
            $case = basename($dir);
            yield $case => [$case, $dir];
        }
    }

    #[DataProvider('fixtureDirectories')]
    public function testFixturePairRunsThroughFinalNewlineFixer(string $case, string $fixtureDir): void
    {
        $old = $fixtureDir . '/old.phpt';
        $new = $fixtureDir . '/new.phpt';
        $diff = $fixtureDir . '/ran.diff';
        $root = $this->makeTempDir();
        $target = $root . '/old.phpt';
        $printed = $root . '/new.phpt';

        self::assertFileExists($old, $case);
        self::assertFileExists($new, $case);
        self::assertFileExists($diff, $case);
        self::assertTrue(copy($old, $target), $case);
        self::assertTrue(copy(self::fixtureRunTestsPath(), $root . '/run-tests.php'), $case);

        $io = new FinalNewlineCapturingConsoleIo();
        $this->withFixtureEnvironment($old, $new, function () use ($io, $root, $target): void {
            $exitCode = new Application($io)->run([
                'php-src-cs.php',
                'fix',
                '--php-src-dir',
                $root,
                '--fixer',
                'final-newline',
                '--print',
                $target,
            ]);

            self::assertSame(0, $exitCode, $io->err);
        });

        file_put_contents($printed, $io->out);

        self::assertSame(file_get_contents($new), $io->out, $case);
        self::assertSame(
            file_get_contents($diff),
            new UnifiedDiff()->betweenFiles($old, $printed, 'old.phpt', 'new.phpt'),
            $case,
        );
    }

    private static function fixturesDir(): string
    {
        return dirname(__DIR__) . '/Fixtures/final_newline';
    }

    private static function fixtureRunTestsPath(): string
    {
        return dirname(__DIR__) . '/Fixtures/run-tests-fake.php';
    }

    private function makeTempDir(): string
    {
        $root = sys_get_temp_dir() . '/final-newline-fixture-' . bin2hex(random_bytes(6));
        mkdir($root);

        return $root;
    }

    private function withFixtureEnvironment(string $old, string $new, callable $callback): void
    {
        $previousOld = $_ENV['FIXTURE_OLD_PHPT'] ?? null;
        $previousNew = $_ENV['FIXTURE_NEW_PHPT'] ?? null;
        $previousPhp = $_ENV['INTERNALS_CS_TEST_PHP_EXECUTABLE'] ?? null;

        $_ENV['FIXTURE_OLD_PHPT'] = $old;
        $_ENV['FIXTURE_NEW_PHPT'] = $new;
        $_ENV['INTERNALS_CS_TEST_PHP_EXECUTABLE'] = PHP_BINARY;

        try {
            $callback();
        } finally {
            if (null === $previousOld) {
                unset($_ENV['FIXTURE_OLD_PHPT']);
            } else {
                $_ENV['FIXTURE_OLD_PHPT'] = $previousOld;
            }

            if (null === $previousNew) {
                unset($_ENV['FIXTURE_NEW_PHPT']);
            } else {
                $_ENV['FIXTURE_NEW_PHPT'] = $previousNew;
            }

            if (null === $previousPhp) {
                unset($_ENV['INTERNALS_CS_TEST_PHP_EXECUTABLE']);
            } else {
                $_ENV['INTERNALS_CS_TEST_PHP_EXECUTABLE'] = $previousPhp;
            }
        }
    }
}

final class FinalNewlineCapturingConsoleIo implements ConsoleIo
{
    public string $out = '';

    public string $err = '';

    public function out(string $message): void
    {
        $this->out .= $message;
    }

    public function err(string $message): void
    {
        $this->err .= $message;
    }
}
