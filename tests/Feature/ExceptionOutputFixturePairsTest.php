<?php

declare(strict_types=1);

namespace Tests\Feature;

use InternalsCS\Console\Application;
use InternalsCS\Console\ConsoleIo;
use InternalsCS\Fixers\FinalNewline\FinalNewline;
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
use function mb_strlen;
use function mb_substr;
use function mkdir;
use function random_bytes;
use function sort;
use function str_contains;
use function sys_get_temp_dir;

final class ExceptionOutputFixturePairsTest extends TestCase
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
    public function testEveryFixtureDirectoryHasCompletePairFiles(string $case, string $fixtureDir): void
    {
        self::assertFileExists($fixtureDir . '/old.phpt', $case);
        self::assertFileExists($fixtureDir . '/new.phpt', $case);
        self::assertFileExists($fixtureDir . '/ran.diff', $case);
    }

    public function testGeneratedFixturePairsDoNotContainLocalRepositoryPaths(): void
    {
        $leaks = [];
        $root = dirname(__DIR__, 2);
        $files = glob(self::fixturesDir() . '/*/{new.phpt,ran.diff}', GLOB_BRACE);

        if (false === $files) {
            $files = [];
        }

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            if (false !== $contents && str_contains($contents, $root)) {
                $leaks[] = mb_substr($file, mb_strlen($root) + 1);
            }
        }

        self::assertSame([], $leaks);
    }

    public function testGeneratedNewFixturesUseFinalNewlineStyle(): void
    {
        $finalNewline = new FinalNewline();
        $badFixtures = [];
        $files = glob(self::fixturesDir() . '/*/new.phpt');

        if (false === $files) {
            $files = [];
        }

        sort($files);

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents, $file);

            if (!$finalNewline->isNormalized($contents)) {
                $badFixtures[] = basename(dirname($file));
            }
        }

        self::assertSame([], $badFixtures);
    }

    #[DataProvider('fixtureDirectories')]
    public function testFixturePairShapeIsConsistent(string $case, string $fixtureDir): void
    {
        $old = $fixtureDir . '/old.phpt';
        $new = $fixtureDir . '/new.phpt';
        $diff = $fixtureDir . '/ran.diff';

        self::assertFileExists($old);
        self::assertFileExists($new);
        self::assertFileExists($diff);

        self::assertSame(
            new UnifiedDiff()->betweenFiles($old, $new, 'old.phpt', 'new.phpt'),
            file_get_contents($diff),
            $case,
        );
    }

    #[DataProvider('fixtureDirectories')]
    public function testFixCommandPrintsExpectedFixtureOutputUsingRealRunTests(string $case, string $fixtureDir): void
    {
        $old = $fixtureDir . '/old.phpt';
        $new = $fixtureDir . '/new.phpt';
        $diff = $fixtureDir . '/ran.diff';
        $tempDir = $this->makeTempDir();
        $target = $tempDir . '/old.phpt';
        $printed = $tempDir . '/new.phpt';

        copy($old, $target);

        $io = new CapturingConsoleIo();
        $exitCode = new Application($io)->run([
            'php-src-cs.php',
            'fix',
            '--php-src-dir',
            self::phpSrcDir(),
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
        return dirname(__DIR__) . '/Fixtures/exception_output_styles';
    }

    private function makeTempDir(): string
    {
        $root = sys_get_temp_dir() . '/exception-output-fixture-' . bin2hex(random_bytes(6));
        mkdir($root);

        return $root;
    }

    private static function phpSrcDir(): string
    {
        $root = dirname(__DIR__, 2) . '/var/php-test-runtime/source';

        self::assertFileExists($root . '/run-tests.php', 'Managed PHP test runtime is not built');
        self::assertTrue(is_executable(dirname($root) . '/php'), 'Managed PHP test CLI is not built');

        return $root;
    }
}

final class CapturingConsoleIo implements ConsoleIo
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
