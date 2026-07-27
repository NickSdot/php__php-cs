<?php

declare(strict_types=1);

namespace Tests\Feature;

use InternalsCS\Console\Application;
use InternalsCS\Console\ConsoleIo;
use InternalsCS\Fixture\FixturePairFiles;
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
use function is_executable;
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
            if (!new FixturePairFiles($dir)->containsFixtureFiles()) {
                continue;
            }

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
        $tempDir = $this->makeTempDir();
        $target = $tempDir . '/old.phpt';
        $printed = $tempDir . '/new.phpt';

        self::assertFileExists($old, $case);
        self::assertFileExists($new, $case);
        self::assertFileExists($diff, $case);
        self::assertTrue(copy($old, $target), $case);

        $io = new FinalNewlineCapturingConsoleIo();
        $exitCode = new Application($io)->run([
            'php-src-cs.php',
            'fix',
            '--php-src-dir',
            self::phpSrcDir(),
            '--fixer',
            'final-newline',
            '--print',
            $target,
        ]);

        self::assertSame(0, $exitCode, $io->err);

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

    private static function phpSrcDir(): string
    {
        $root = dirname(__DIR__, 2) . '/var/php-test-runtime/source';

        self::assertFileExists($root . '/run-tests.php', 'Managed PHP test runtime is not built');
        self::assertTrue(is_executable(dirname($root) . '/php'), 'Managed PHP test CLI is not built');

        return $root;
    }

    private function makeTempDir(): string
    {
        $root = sys_get_temp_dir() . '/final-newline-fixture-' . bin2hex(random_bytes(6));
        mkdir($root);

        return $root;
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
