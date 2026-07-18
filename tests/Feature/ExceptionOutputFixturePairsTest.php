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
use function is_file;
use function mb_strlen;
use function mb_substr;
use function mkdir;
use function random_bytes;
use function str_contains;
use function sys_get_temp_dir;

final class ExceptionOutputFixturePairsTest extends TestCase
{
    /**
     * Fixture dirs expected to have stable command-reproducible old/new/ran.diff pairs.
     *
     * Existing fixture pairs may stay outside this registry while the fixer is
     * being ported. Adding a case here means the command must reproduce it.
     *
     * @var list<string>
     */
    private const array STABLE_CASES = [
        'Zend_tests_access_modifiers_access_modifiers_008',
        'Zend_tests_add_002',
        'Zend_tests_arg_unpack_invalid_type',
        'Zend_tests_arg_unpack_string_keys',
        'Zend_tests_array_literal_next_element_error',
        'Zend_tests_arrow_functions_007',
        'Zend_tests_assert_bug71922',
        'Zend_tests_ast_gh21072',
        'Zend_tests_attributes_delayed_target_validation_has_runtime_errors',
        'Zend_tests_bug53432',
        'Zend_tests_constants_008',
        'Zend_tests_enum_backed_mismatch',
        'Zend_tests_exit_finally_2',
        'Zend_tests_closures_closure_instantiate',
        'Zend_tests_generators_generator_throwing_during_function_call',
        'Zend_tests_generators_throw_into_yield_from_array',
        'Zend_tests_match_043',
        'Zend_tests_require_parse_exception',
        'Zend_tests_return_types_028',
        'ext_dom_tests_DOMNode_removeChild_error1',
        'ext_dom_tests_DOMElement_className',
        'ext_fileinfo_tests_finfo_open_003',
        'ext_hash_tests_hash_equals',
        'ext_intl_tests_calendar_toDateTime_error',
        'ext_spl_tests_iterator_047',
        'ext_spl_tests_multiple_iterator_001',
        'ext_standard_tests_array_bug42177',
        'ext_standard_tests_strings_vfprintf_error4',
        'ext_tokenizer_tests_token_get_all_heredoc_nowdoc',
        'tests_classes_array_access_013',
        'tests_lang_foreachLoopIteratorAggregate_002',
        'tests_strings_002',
    ];

    /** @return iterable<string, array{string}> */
    public static function fixtureDirs(): iterable
    {
        $dirs = glob(self::fixturesDir() . '/*', GLOB_ONLYDIR);

        if (false === $dirs) {
            return;
        }

        foreach ($dirs as $dir) {
            yield basename($dir) => [$dir];
        }
    }

    /** @return iterable<string, array{string}> */
    public static function stableCases(): iterable
    {
        foreach (self::STABLE_CASES as $case) {
            yield $case => [$case];
        }
    }

    #[DataProvider('fixtureDirs')]
    public function testEveryFixtureDirectoryHasOldFixture(string $fixtureDir): void
    {
        self::assertFileExists($fixtureDir . '/old.phpt');
    }

    public function testStableRegistryPairFilesExist(): void
    {
        $missing = [];

        foreach (self::STABLE_CASES as $case) {
            $fixtureDir = self::fixturesDir() . '/' . $case;

            if (!is_file($fixtureDir . '/new.phpt') || !is_file($fixtureDir . '/ran.diff')) {
                $missing[] = $case;
            }
        }

        self::assertSame([], $missing);
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

    #[DataProvider('stableCases')]
    public function testRegisteredFixturePairShapeIsConsistent(string $case): void
    {
        $fixtureDir = self::fixturesDir() . '/' . $case;
        $old = $fixtureDir . '/old.phpt';
        $new = $fixtureDir . '/new.phpt';
        $diff = $fixtureDir . '/ran.diff';

        self::assertFileExists($old);
        self::assertFileExists($new);
        self::assertFileExists($diff);

        self::assertSame(
            new UnifiedDiff()->betweenFiles($old, $new, 'old.phpt', 'new.phpt'),
            file_get_contents($diff),
            basename($fixtureDir),
        );
    }

    #[DataProvider('stableCases')]
    public function testFixCommandPrintsExpectedFixtureOutputUsingFixtureRunTests(string $case): void
    {
        $fixtureDir = self::fixturesDir() . '/' . $case;
        $old = $fixtureDir . '/old.phpt';
        $new = $fixtureDir . '/new.phpt';
        $diff = $fixtureDir . '/ran.diff';
        $root = $this->makeTempDir();
        $target = $root . '/old.phpt';
        $printed = $root . '/new.phpt';

        copy($old, $target);
        $this->installFixtureRunTests($root);

        $io = new CapturingConsoleIo();
        $this->withFixtureEnvironment($old, $new, function () use ($io, $root, $target): void {
            $exitCode = new Application($io)->run([
                'php-cs.php',
                'fix',
                '--php-src-dir',
                $root,
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
        return dirname(__DIR__) . '/Fixtures/exception_output_styles';
    }

    private function makeTempDir(): string
    {
        $root = sys_get_temp_dir() . '/exception-output-fixture-' . bin2hex(random_bytes(6));
        mkdir($root);

        return $root;
    }

    private function installFixtureRunTests(string $root): void
    {
        self::assertTrue(copy(self::fixtureRunTestsPath(), $root . '/run-tests.php'));
    }

    private static function fixtureRunTestsPath(): string
    {
        return dirname(__DIR__) . '/Fixtures/run-tests-fake.php';
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
