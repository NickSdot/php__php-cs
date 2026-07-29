<?php

declare(strict_types=1);

namespace Tests\Unit;

use InternalsCS\Console\ConsoleIo;
use InternalsCS\Fixers\ExceptionOutput\Generation\CandidateCollector;
use InternalsCS\Fixers\ExceptionOutput\Generation\FixtureReportWriter;
use InternalsCS\Fixers\ExceptionOutput\Generation\SourceVerifier;
use InternalsCS\Fixture\FixtureCaseName;
use InternalsCS\Fixture\FixtureDiscovery;
use InternalsCS\Fixture\FixtureGenerationOptions;
use InternalsCS\Fixture\FixtureGenerationResult;
use InternalsCS\Fixture\FixtureGenerator;
use InternalsCS\Fixture\FixtureOriginalRunner;
use InternalsCS\Fixture\FixtureReporter;
use InternalsCS\Fixture\FixtureRewriteRunner;
use InternalsCS\Fixture\FixtureSource;
use InternalsCS\Fixture\FixtureSourceReducer;
use InternalsCS\Fixture\FixtureSourceRunVerifier;
use InternalsCS\Fixture\FixtureSourceVerification;
use InternalsCS\Fixture\FixtureSourceVerifier;
use InternalsCS\PhpSrc\PhpSrcRoot;
use InternalsCS\SourceFile;
use PHPUnit\Framework\TestCase;

use function basename;
use function bin2hex;
use function count;
use function dirname;
use function escapeshellarg;
use function exec;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function implode;
use function is_file;
use function mkdir;
use function preg_match;
use function random_bytes;
use function rename;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function sys_get_temp_dir;

final class FixtureGeneratorTest extends TestCase
{
    public function testDryRunGroupsDuplicateCandidateWindowsWithoutWritingFixtures(): void
    {
        $root = $this->makeTempDir();
        $fixtures = $root . '/fixtures';
        $phpSrc = $root . '/php-src';
        mkdir($fixtures);
        mkdir($phpSrc);
        file_put_contents($phpSrc . '/run-tests.php', '<?php');

        $this->writeSourcePhpt($phpSrc, 'a.phpt', 'echo "Caught " . $e->getMessage() . "\n";');
        $this->writeSourcePhpt($phpSrc, 'b.phpt', 'echo "*** Caught " . $e->getMessage() . "\n";');

        $result = $this->generateOne(
            phpSrc: $phpSrc,
            fixtures: $fixtures,
            reports: $fixtures,
            runner: new NoopFixtureRewriteRunner(),
            write: false,
            refreshOnly: false,
        );

        self::assertSame(2, $result->candidateFiles);
        self::assertSame(2, $result->candidateWindows);
        self::assertSame(1, $result->candidateFlavours);
        self::assertSame(1, $result->duplicateCandidates);
        self::assertSame(1, $result->selectedFixtures);
        self::assertSame([], glob($fixtures . '/*', GLOB_ONLYDIR));
    }

    public function testDryRunSelectsOneFixtureForMultipleConcreteFlavoursInSameFixtureCase(): void
    {
        $root = $this->makeTempDir();
        $fixtures = $root . '/fixtures';
        $phpSrc = $root . '/php-src';
        mkdir($fixtures);
        mkdir($phpSrc);
        file_put_contents($phpSrc . '/run-tests.php', '<?php');

        $this->writeSourcePhpt($phpSrc, 'a.phpt', 'echo "Serialization failed: " . $e->getMessage() . "\n";');
        $this->writeSourcePhpt($phpSrc, 'b.phpt', 'echo "Object-based creation failed as expected: " . $e->getMessage() . "\n";');

        $result = $this->generateOne(
            phpSrc: $phpSrc,
            fixtures: $fixtures,
            reports: $fixtures,
            runner: new NoopFixtureRewriteRunner(),
            write: false,
            refreshOnly: false,
        );

        self::assertSame(2, $result->candidateFiles);
        self::assertSame(2, $result->candidateWindows);
        self::assertSame(2, $result->candidateFlavours);
        self::assertSame(0, $result->duplicateCandidates);
        self::assertSame(1, $result->selectedFixtures);
    }

    public function testDryRunIgnoresNoOpExceptionOutputWindows(): void
    {
        $root = $this->makeTempDir();
        $fixtures = $root . '/fixtures';
        $phpSrc = $root . '/php-src';
        mkdir($fixtures);
        mkdir($phpSrc);
        file_put_contents($phpSrc . '/run-tests.php', '<?php');

        $this->writeSourcePhptWithStatements($phpSrc, 'a.phpt', [
            'echo $e->getMessage(), "\n";',
            'echo $e::class, \': \', $e->getMessage(), PHP_EOL;',
        ]);

        $result = $this->generateOne(
            phpSrc: $phpSrc,
            fixtures: $fixtures,
            reports: $fixtures,
            runner: new NoopFixtureRewriteRunner(),
            write: false,
            refreshOnly: false,
        );

        self::assertSame(1, $result->candidateFiles);
        self::assertSame(1, $result->candidateWindows);
        self::assertSame(1, $result->candidateFlavours);
        self::assertSame(1, $result->selectedFixtures);
    }

    public function testDirtySourceRefreshesExistingFixturesWithoutUpdatingDiscoveryReports(): void
    {
        $root = $this->makeTempDir();
        $fixtures = $root . '/fixtures';
        $reports = $root . '/reports';
        $phpSrc = $root . '/php-src';
        mkdir($fixtures);
        mkdir($reports);
        mkdir($phpSrc);
        mkdir($fixtures . '/case');
        file_put_contents($fixtures . '/case/old.phpt', "old\n");
        file_put_contents($reports . '/stats.md', "existing stats\n");
        $this->markGitDirty($phpSrc);

        $result = $this->generateOne(
            phpSrc: $phpSrc,
            fixtures: $fixtures,
            reports: $reports,
            runner: new ChangedFixtureRewriteRunner("new\n"),
            write: true,
            refreshOnly: false,
            allowDirty: false,
        );

        self::assertTrue($result->refreshOnly);
        self::assertSame([
            'source checkout is dirty; skipped source discovery and old.phpt import; pass --allow-dirty to generate from dirty source',
        ], $result->warnings);
        self::assertSame(0, $result->scannedFiles);
        self::assertSame(0, $result->createdOld);
        self::assertSame(1, $result->updatedPairs);
        self::assertSame(0, $result->oldOnly);
        self::assertSame(['case'], $result->updatedPairCases);
        self::assertSame("old\n", file_get_contents($fixtures . '/case/old.phpt'));
        self::assertSame("new\n", file_get_contents($fixtures . '/case/new.phpt'));
        self::assertTrue(is_file($fixtures . '/case/ran.diff'));
        self::assertSame("existing stats\n", file_get_contents($reports . '/stats.md'));
        self::assertStringContainsString('- case', (string) file_get_contents($reports . '/refresh.txt'));
    }

    public function testRefreshOnlyRefreshesExistingFixturesAndRecomputesDiscoveryReports(): void
    {
        $root = $this->makeTempDir();
        $fixtures = $root . '/fixtures';
        $reports = $root . '/reports';
        $phpSrc = $root . '/php-src';
        mkdir($fixtures);
        mkdir($reports);
        mkdir($phpSrc);
        mkdir($fixtures . '/case');
        $this->writeSourcePhpt($phpSrc, 'source.phpt', 'echo "Caught " . $e->getMessage() . "\n";');
        file_put_contents($fixtures . '/case/old.phpt', "old\n");

        $result = $this->generateOne(
            phpSrc: $phpSrc,
            fixtures: $fixtures,
            reports: $reports,
            runner: new ChangedFixtureRewriteRunner("new\n"),
            write: true,
            refreshOnly: true,
        );

        self::assertTrue($result->refreshOnly);
        self::assertSame(1, $result->scannedFiles);
        self::assertSame(1, $result->selectedFixtures);
        self::assertSame(1, $result->updatedPairs);
        self::assertSame(['case'], $result->updatedPairCases);
        self::assertSame("old\n", file_get_contents($fixtures . '/case/old.phpt'));
        self::assertSame("new\n", file_get_contents($fixtures . '/case/new.phpt'));
        self::assertTrue(is_file($fixtures . '/case/ran.diff'));
        self::assertTrue(is_file($reports . '/refresh.txt'));
        self::assertMatchesRegularExpression(
            '/\| Scanned source files\s+\|\s+1\s+\|/',
            (string) file_get_contents($reports . '/stats.md'),
        );
        self::assertMatchesRegularExpression(
            '/\| Status\s+\| Flavour\s+\| Fixture\s+\| Detail\s+\| Fingerprint\s+\|/',
            (string) file_get_contents($reports . '/stats.md'),
        );
        self::assertStringContainsString('source.phpt', (string) file_get_contents($reports . '/stats.md'));
    }

    public function testRefreshOnlyReportsConcreteFlavoursCoveredBySharedFixtureCase(): void
    {
        $root = $this->makeTempDir();
        $fixtures = $root . '/fixtures';
        $reports = $root . '/reports';
        $phpSrc = $root . '/php-src';
        mkdir($fixtures);
        mkdir($reports);
        mkdir($phpSrc);

        $this->writeSourcePhpt($phpSrc, 'a.phpt', 'echo "Serialization failed: " . $e->getMessage() . "\n";');
        $this->writeSourcePhpt($phpSrc, 'b.phpt', 'echo "Object-based creation failed as expected: " . $e->getMessage() . "\n";');
        $this->writeFixturePhpt($fixtures, 'echo "Serialization failed: " . $e->getMessage() . "\n";');

        $result = $this->generateOne(
            phpSrc: $phpSrc,
            fixtures: $fixtures,
            reports: $reports,
            runner: new ChangedFixtureRewriteRunner("new\n"),
            write: true,
            refreshOnly: true,
            sourceVerifier: new ExistingFixtureOnlyVerifier(),
        );

        self::assertSame(2, $result->candidateFlavours);
        self::assertSame(1, $result->selectedFixtures);
        self::assertMatchesRegularExpression('/\| done\s+\|\s+2\s+\|/', (string) file_get_contents($reports . '/stats.md'));
        self::assertMatchesRegularExpression('/\| open\s+\|\s+0\s+\|/', (string) file_get_contents($reports . '/stats.md'));
        self::assertStringContainsString('flavours covered: 2', (string) file_get_contents($reports . '/fixtures.txt'));
    }

    public function testWriteRunReportsPostRefreshHandledFixtureState(): void
    {
        $root = $this->makeTempDir();
        $fixtures = $root . '/fixtures';
        $reports = $root . '/reports';
        $phpSrc = $root . '/php-src';
        mkdir($fixtures);
        mkdir($reports);
        mkdir($phpSrc);
        file_put_contents($phpSrc . '/run-tests.php', '<?php');

        $sourcePath = $this->writeSourcePhpt($phpSrc, 'source.phpt', 'echo $e->getMessage(), "\n";');
        $this->writeFixtureFromFile($fixtures, $sourcePath);

        $this->generateOne(
            phpSrc: $phpSrc,
            fixtures: $fixtures,
            reports: $reports,
            runner: new ChangedFixtureRewriteRunner("new\n"),
            write: true,
            refreshOnly: false,
        );

        self::assertMatchesRegularExpression(
            '/\| done\s+\|\s+1\s+\|/',
            (string) file_get_contents($reports . '/stats.md'),
        );
        self::assertMatchesRegularExpression(
            '/\| open\s+\|\s+0\s+\|/',
            (string) file_get_contents($reports . '/stats.md'),
        );
    }

    public function testWriteRunReportsStaleFixturePairsAsOpen(): void
    {
        $root = $this->makeTempDir();
        $fixtures = $root . '/fixtures';
        $reports = $root . '/reports';
        $phpSrc = $root . '/php-src';
        mkdir($fixtures);
        mkdir($reports);
        mkdir($phpSrc);
        file_put_contents($phpSrc . '/run-tests.php', '<?php');

        $sourcePath = $this->writeSourcePhpt($phpSrc, 'source.phpt', 'echo $e->getMessage(), "\n";');
        $case = $this->writeFixtureFromFile($fixtures, $sourcePath);
        file_put_contents($fixtures . '/' . $case . '/new.phpt', "new\n");
        file_put_contents($fixtures . '/' . $case . '/ran.diff', "diff\n");

        $this->generateOne(
            phpSrc: $phpSrc,
            fixtures: $fixtures,
            reports: $reports,
            runner: new NoopFixtureRewriteRunner(),
            write: true,
            refreshOnly: false,
        );

        self::assertMatchesRegularExpression(
            '/\| open\s+\|\s+1\s+\|/',
            (string) file_get_contents($reports . '/stats.md'),
        );
        self::assertStringContainsString('stale_pair_kept; ' . $case . '/old.phpt:8', (string) file_get_contents($reports . '/stats.md'));
    }

    public function testWriteRunPrefersExistingFixtureOverMatchingSourceCandidate(): void
    {
        $root = $this->makeTempDir();
        $fixtures = $root . '/fixtures';
        $reports = $root . '/reports';
        $phpSrc = $root . '/php-src';
        mkdir($fixtures);
        mkdir($reports);
        mkdir($phpSrc);

        $statement = 'echo $e->getMessage(), "\n";';
        $this->writeSourcePhpt($phpSrc, 'source.phpt', $statement);
        $case = $this->writeFixturePhpt($fixtures, $statement);

        $result = $this->generateOne(
            phpSrc: $phpSrc,
            fixtures: $fixtures,
            reports: $reports,
            runner: new NoopFixtureRewriteRunner(),
            write: true,
            refreshOnly: false,
        );

        self::assertSame(1, $result->selectedFixtures);
        self::assertStringContainsString($case, (string) file_get_contents($reports . '/stats.md'));
    }

    public function testWriteRunRejectsSourceThatNeedsExternalFileAndSelectsNextSource(): void
    {
        $root = $this->makeTempDir();
        $fixtures = $root . '/fixtures';
        $reports = $root . '/reports';
        $phpSrc = $root . '/php-src';
        mkdir($fixtures);
        mkdir($reports);
        mkdir($phpSrc);
        file_put_contents($phpSrc . '/dependency.inc', '<?php');
        $rejectedSource = $this->writeSourcePhptWithStatements($phpSrc, 'a.phpt', [
            "require __DIR__ . '/dependency.inc';",
            'echo $e->getMessage(), "\n";',
        ]);
        $case = $this->writeFixtureFromFile($fixtures, $rejectedSource);
        file_put_contents($fixtures . '/' . $case . '/new.phpt', "new\n");
        file_put_contents($fixtures . '/' . $case . '/ran.diff', "diff\n");
        $this->writeSourcePhpt($phpSrc, 'b.phpt', 'echo $e->getMessage(), "\n";');

        $result = $this->generateOne(
            phpSrc: $phpSrc,
            fixtures: $fixtures,
            reports: $reports,
            runner: new FixtureRunnerStub(
                originalPasses: static function (string $path): bool {
                    $contents = (string) file_get_contents($path);
                    $requiresDependency = str_contains($contents, "require __DIR__ . '/dependency.inc';");

                    return !$requiresDependency || is_file(dirname($path) . '/dependency.inc');
                },
            ),
            write: true,
            refreshOnly: false,
        );

        $oldFixtures = glob($fixtures . '/*/old.phpt');

        self::assertIsArray($oldFixtures);
        self::assertCount(1, $oldFixtures);
        self::assertStringContainsString('b.phpt', (string) file_get_contents($oldFixtures[0]));
        self::assertStringContainsString('b.phpt', (string) file_get_contents($reports . '/fixtures.txt'));
        self::assertStringNotContainsString('a.phpt', (string) file_get_contents($reports . '/fixtures.txt'));
        self::assertDirectoryExists($fixtures . '/' . $case);
        self::assertStringContainsString('b.phpt', (string) file_get_contents($fixtures . '/' . $case . '/old.phpt'));
        self::assertSame(0, $result->removedFixtures);
        self::assertSame([], $result->removedFixtureCases);
    }

    public function testWriteRunRejectsSourceThatNeedsOriginalFilenameAndSelectsNextSource(): void
    {
        $root = $this->makeTempDir();
        $fixtures = $root . '/fixtures';
        $reports = $root . '/reports';
        $phpSrc = $root . '/php-src';
        mkdir($fixtures);
        mkdir($reports);
        mkdir($phpSrc);

        $statement = 'echo $e->getMessage(), "\n";';
        $this->writeSourcePhptWithExpected($phpSrc, 'a.phpt', [$statement], "Expected script: a.php\n");
        $this->writeSourcePhptWithExpected($phpSrc, 'b.phpt', [$statement], "Expected script: old.php\n");

        $this->generateOne(
            phpSrc: $phpSrc,
            fixtures: $fixtures,
            reports: $reports,
            runner: new FixtureRunnerStub(
                originalPasses: static function (string $path): bool {
                    $contents = (string) file_get_contents($path);
                    $matched = preg_match('/^Expected script: ([^\r\n]+)$/m', $contents, $matches);

                    return 1 !== $matched || basename($path, '.phpt') . '.php' === $matches[1];
                },
            ),
            write: true,
            refreshOnly: false,
        );

        $oldFixtures = glob($fixtures . '/*/old.phpt');

        self::assertIsArray($oldFixtures);
        self::assertCount(1, $oldFixtures);
        self::assertStringContainsString('b.phpt', (string) file_get_contents($oldFixtures[0]));
        self::assertStringContainsString('b.phpt', (string) file_get_contents($reports . '/fixtures.txt'));
        self::assertStringNotContainsString('a.phpt', (string) file_get_contents($reports . '/fixtures.txt'));
    }

    public function testWriteRunRejectsSourceWithSkipIfAndSelectsNextSource(): void
    {
        $root = $this->makeTempDir();
        $fixtures = $root . '/fixtures';
        $reports = $root . '/reports';
        $phpSrc = $root . '/php-src';
        mkdir($fixtures);
        mkdir($reports);
        mkdir($phpSrc);

        $path = $this->writeSourcePhpt($phpSrc, 'a.phpt', 'echo $e->getMessage(), "\n";');
        $contents = (string) file_get_contents($path);
        file_put_contents(
            $path,
            str_replace("--FILE--\n", "--SKIPIF--\n<?php die('skip'); ?>\n--FILE--\n", $contents),
        );
        $this->writeSourcePhpt($phpSrc, 'b.phpt', 'echo $e->getMessage(), "\n";');

        $this->generateOne(
            phpSrc: $phpSrc,
            fixtures: $fixtures,
            reports: $reports,
            runner: new FixtureRunnerStub(),
            write: true,
            refreshOnly: false,
        );

        $oldFixtures = glob($fixtures . '/*/old.phpt');

        self::assertIsArray($oldFixtures);
        self::assertCount(1, $oldFixtures);
        self::assertStringContainsString('b.phpt', (string) file_get_contents($oldFixtures[0]));
        self::assertStringNotContainsString('a.phpt', (string) file_get_contents($reports . '/fixtures.txt'));
    }

    public function testWriteRunDoesNotRemoveRejectedFixtureWithoutReplacementCoverage(): void
    {
        $root = $this->makeTempDir();
        $fixtures = $root . '/fixtures';
        $reports = $root . '/reports';
        $phpSrc = $root . '/php-src';
        mkdir($fixtures);
        mkdir($reports);
        mkdir($phpSrc);
        file_put_contents($phpSrc . '/dependency.inc', '<?php');
        $source = $this->writeSourcePhptWithStatements($phpSrc, 'a.phpt', [
            "require __DIR__ . '/dependency.inc';",
            'echo $e->getMessage(), "\n";',
        ]);
        $case = $this->writeFixtureFromFile($fixtures, $source);
        file_put_contents($fixtures . '/' . $case . '/new.phpt', "new\n");
        file_put_contents($fixtures . '/' . $case . '/ran.diff', "diff\n");
        file_put_contents($reports . '/stats.md', "unchanged\n");

        $result = $this->generateOne(
            phpSrc: $phpSrc,
            fixtures: $fixtures,
            reports: $reports,
            runner: new FixtureRunnerStub(
                originalPasses: static function (string $path): bool {
                    $contents = (string) file_get_contents($path);
                    $requiresDependency = str_contains($contents, "require __DIR__ . '/dependency.inc';");

                    return !$requiresDependency || is_file(dirname($path) . '/dependency.inc');
                },
            ),
            write: true,
            refreshOnly: false,
        );

        self::assertTrue($result->failed());
        self::assertCount(1, $result->failures);
        self::assertStringContainsString('no verified fixture source for flavour', $result->failures[0]);
        self::assertSame(0, $result->removedFixtures);
        self::assertFileExists($fixtures . '/' . $case . '/old.phpt');
        self::assertFileExists($fixtures . '/' . $case . '/new.phpt');
        self::assertFileExists($fixtures . '/' . $case . '/ran.diff');
        self::assertSame("unchanged\n", file_get_contents($reports . '/stats.md'));
    }

    public function testWriteRunRemovesExistingReducerFixtureWithoutSourceFlavour(): void
    {
        $root = $this->makeTempDir();
        $fixtures = $root . '/fixtures';
        $reports = $root . '/reports';
        $phpSrc = $root . '/php-src';
        mkdir($fixtures);
        mkdir($reports);
        mkdir($phpSrc);
        $case = $this->writeFixturePhpt($fixtures, 'echo $e->getMessage(), "\n";');
        file_put_contents($fixtures . '/' . $case . '/new.phpt', (string) file_get_contents($fixtures . '/' . $case . '/old.phpt'));
        file_put_contents($fixtures . '/' . $case . '/ran.diff', '');

        $result = $this->generateOne(
            phpSrc: $phpSrc,
            fixtures: $fixtures,
            reports: $reports,
            runner: new FixtureRunnerStub(),
            write: true,
            refreshOnly: false,
            sourceReducer: new FixedFixtureSourceReducer("unused\n"),
        );

        self::assertFalse($result->failed());
        self::assertSame(0, $result->selectedFixtures);
        self::assertSame(1, $result->removedFixtures);
        self::assertDirectoryDoesNotExist($fixtures . '/' . $case);
    }

    public function testWriteRunUsesExistingFixtureWhenNoSourceRepresentativeRuns(): void
    {
        $root = $this->makeTempDir();
        $fixtures = $root . '/fixtures';
        $reports = $root . '/reports';
        $phpSrc = $root . '/php-src';
        $runtime = $root . '/runtime';
        mkdir($fixtures);
        mkdir($reports);
        mkdir($phpSrc);
        mkdir($runtime);

        $statement = 'echo "[009] ".$e->getMessage()."\n";';
        $this->writeSourcePhpt($phpSrc, 'a.phpt', $statement);
        $case = $this->writeFixturePhpt($fixtures, $statement);

        $this->generateOne(
            phpSrc: $phpSrc,
            fixtures: $fixtures,
            reports: $reports,
            runner: new FixtureRunnerStub(
                originalPasses: static fn(string $path): bool => !str_contains(
                    (string) file_get_contents($path),
                    "--TEST--\na.phpt\n",
                ),
            ),
            write: true,
            refreshOnly: false,
            runtime: $runtime,
        );

        self::assertFileExists($fixtures . '/' . $case . '/old.phpt');
        self::assertStringContainsString($case . '/old.phpt', (string) file_get_contents($reports . '/fixtures.txt'));
        self::assertStringContainsString('old_only_fixture', (string) file_get_contents($reports . '/stats.md'));
        self::assertStringNotContainsString('no_selected_runnable_dir', (string) file_get_contents($reports . '/stats.md'));
    }

    public function testWriteRunSkipsSourceWhenExpectedOutputDoesNotExerciseCandidate(): void
    {
        $root = $this->makeTempDir();
        $fixtures = $root . '/fixtures';
        $reports = $root . '/reports';
        $phpSrc = $root . '/php-src';
        $runtime = $root . '/runtime';
        mkdir($fixtures);
        mkdir($reports);
        mkdir($phpSrc);
        mkdir($runtime);

        $statement = 'echo "SoapFault: " . $e->getMessage() . "\n";';
        $this->writeSourcePhptWithExpected($phpSrc, 'dead.phpt', [$statement], "redirect followed\n");
        $this->writeSourcePhptWithExpected($phpSrc, 'live.phpt', [$statement], "SoapFault: broken\n");

        $this->generateOne(
            phpSrc: $phpSrc,
            fixtures: $fixtures,
            reports: $reports,
            runner: new FixtureRunnerStub(
                rewrite: static fn(string $path): string => "rewritten\n",
            ),
            write: true,
            refreshOnly: false,
            runtime: $runtime,
            sourceVerifier: new SourceVerifier(),
        );

        $oldFixtures = glob($fixtures . '/*/old.phpt');

        self::assertIsArray($oldFixtures);
        self::assertCount(1, $oldFixtures);
        self::assertStringContainsString('live.phpt', (string) file_get_contents($oldFixtures[0]));
        self::assertStringNotContainsString('dead.phpt', (string) file_get_contents($reports . '/fixtures.txt'));
    }

    public function testWriteRunSelectsNextSourceWhenFirstRunnableCandidateDoesNotRewrite(): void
    {
        $root = $this->makeTempDir();
        $fixtures = $root . '/fixtures';
        $reports = $root . '/reports';
        $phpSrc = $root . '/php-src';
        mkdir($fixtures);
        mkdir($reports);
        mkdir($phpSrc);

        $statement = 'echo $e->getMessage(), "\n";';
        $this->writeSourcePhptWithExpected($phpSrc, 'a.phpt', [$statement], "broken\n");
        $this->writeSourcePhptWithExpected($phpSrc, 'b.phpt', [$statement], "broken\n");

        $result = $this->generateOne(
            phpSrc: $phpSrc,
            fixtures: $fixtures,
            reports: $reports,
            runner: new FixtureRunnerStub(
                rewrite: static function (string $path): ?string {
                    $contents = (string) file_get_contents($path);

                    return str_contains($contents, "--TEST--\na.phpt\n")
                        ? null
                        : $contents . "\n";
                },
            ),
            write: true,
            refreshOnly: false,
            sourceVerifier: new SourceVerifier(),
        );

        $oldFixtures = glob($fixtures . '/*/old.phpt');

        self::assertIsArray($oldFixtures);
        self::assertCount(1, $oldFixtures);
        self::assertStringContainsString('b.phpt', (string) file_get_contents($oldFixtures[0]));
        self::assertStringNotContainsString('a.phpt', (string) file_get_contents($reports . '/fixtures.txt'));
        self::assertSame(0, $result->oldOnly);
        self::assertSame(1, $result->updatedPairs);
    }

    public function testWriteRunUsesReducedFixtureBeforeSourceVerifier(): void
    {
        $root = $this->makeTempDir();
        $fixtures = $root . '/fixtures';
        $reports = $root . '/reports';
        $phpSrc = $root . '/php-src';
        mkdir($fixtures);
        mkdir($reports);
        mkdir($phpSrc);
        $this->writeSourcePhpt($phpSrc, 'source.phpt', 'echo $e->getMessage(), "\n";');

        $result = $this->generateOne(
            phpSrc: $phpSrc,
            fixtures: $fixtures,
            reports: $reports,
            runner: new ChangedFixtureRewriteRunner("new\n"),
            write: true,
            refreshOnly: false,
            sourceVerifier: new RejectingFixtureSourceVerifier(),
            sourceReducer: new FixedFixtureSourceReducer("reduced old\n"),
        );

        $oldFixtures = glob($fixtures . '/*/old.phpt');

        self::assertIsArray($oldFixtures);
        self::assertCount(1, $oldFixtures);
        self::assertFalse($result->failed());
        self::assertSame("reduced old\n", file_get_contents($oldFixtures[0]));
    }

    public function testWriteRunCachesReducedFixtureContentsByFixtureCase(): void
    {
        $root = $this->makeTempDir();
        $fixtures = $root . '/fixtures';
        $reports = $root . '/reports';
        $phpSrc = $root . '/php-src';
        mkdir($fixtures);
        mkdir($reports);
        mkdir($phpSrc);

        $this->writeSourcePhptWithStatements($phpSrc, 'source.phpt', [
            'echo "first: ", $e->getMessage(), "\n";',
            'echo "second: " . $e->getMessage() . "\n";',
        ]);

        $result = $this->generateOne(
            phpSrc: $phpSrc,
            fixtures: $fixtures,
            reports: $reports,
            runner: new ChangedFixtureRewriteRunner("new\n"),
            write: true,
            refreshOnly: false,
            sourceReducer: new CaseNameFixtureSourceReducer(),
        );

        $oldFixtures = glob($fixtures . '/*/old.phpt');

        self::assertIsArray($oldFixtures);
        self::assertCount(2, $oldFixtures);
        self::assertFalse($result->failed());

        foreach ($oldFixtures as $oldFixture) {
            $case = basename(dirname($oldFixture));

            self::assertSame("fixture: $case\n", file_get_contents($oldFixture));
        }
    }

    private function writeSourcePhpt(string $root, string $name, string $statement): string
    {
        return $this->writeSourcePhptWithStatements($root, $name, [$statement]);
    }

    /** @param list<string> $statements */
    private function writeSourcePhptWithStatements(string $root, string $name, array $statements): string
    {
        return $this->writeSourcePhptWithExpected($root, $name, $statements, '');
    }

    /** @param list<string> $statements */
    private function writeSourcePhptWithExpected(string $root, string $name, array $statements, string $expected): string
    {
        $body = $this->indentedStatements($statements);

        $contents = <<<PHPT
            --TEST--
            $name
            --FILE--
            <?php
            try {
                throw new RuntimeException('broken');
            } catch (Throwable \$e) {
                $body
            }
            --EXPECT--
            $expected

            PHPT;

        $path = $root . '/' . $name;
        file_put_contents($path, $contents);

        return $path;
    }

    private function writeFixturePhpt(string $fixtures, string $statement): string
    {
        $seedCase = 'seed_' . bin2hex(random_bytes(4));
        $seedDir = $fixtures . '/' . $seedCase;
        mkdir($seedDir);

        $contents = <<<PHPT
            --TEST--
            Exception-output fixture
            --FILE--
            <?php
            try {
                throw new RuntimeException('fixture message');
            } catch (Throwable \$e) {
                $statement
            }
            --EXPECT--
            [009] fixture message

            PHPT;

        $oldPath = $seedDir . '/old.phpt';
        file_put_contents($oldPath, $contents);

        $candidates = new CandidateCollector(requireExpectedOutputEvidence: false)->collect(new SourceFile($oldPath, $fixtures));
        self::assertCount(1, $candidates);

        $case = new FixtureCaseName()->fromCandidate($candidates[0]);
        $fixtureDir = $fixtures . '/' . $case;
        rename($seedDir, $fixtureDir);

        return $case;
    }

    private function writeFixtureFromFile(string $fixtures, string $sourcePath): string
    {
        $candidates = new CandidateCollector(requireExpectedOutputEvidence: false)->collect(new SourceFile($sourcePath, dirname($sourcePath)));
        self::assertGreaterThanOrEqual(1, count($candidates));

        $case = new FixtureCaseName()->fromCandidate($candidates[0]);
        mkdir($fixtures . '/' . $case);
        file_put_contents($fixtures . '/' . $case . '/old.phpt', (string) file_get_contents($sourcePath));

        return $case;
    }

    /** @param list<string> $statements */
    private function indentedStatements(array $statements): string
    {
        return implode("\n    ", $statements);
    }

    private function makeTempDir(): string
    {
        $root = sys_get_temp_dir() . '/fixture-generator-' . bin2hex(random_bytes(6));
        mkdir($root);

        return $root;
    }

    private function generateOne(
        string $phpSrc,
        string $fixtures,
        string $reports,
        FixtureRewriteRunner $runner,
        bool $write,
        bool $refreshOnly,
        ?string $runtime = null,
        bool $allowDirty = true,
        ?FixtureReporter $reporter = null,
        ?FixtureSourceVerifier $sourceVerifier = null,
        ?FixtureSourceReducer $sourceReducer = null,
    ): FixtureGenerationResult {
        $runtime ??= $phpSrc;
        $this->ensurePhpSrcRoot($phpSrc);
        $this->ensurePhpSrcRoot($runtime);

        $result = $this->generator()->generate(new FixtureGenerationOptions(
            phpSrcRoot: PhpSrcRoot::fromPath($phpSrc),
            phpTestRuntimeRoot: PhpSrcRoot::fromPath($runtime),
            fixturesRoot: $fixtures,
            reportsRoot: $reports,
            paths: [],
            allowDirty: $allowDirty,
            write: $write,
            refreshOnly: $refreshOnly,
        ), [
            new TestFixtureDiscovery(
                runner: $runner,
                reporter: $reporter ?? new FixtureReportWriter(),
                sourceVerifier: $sourceVerifier ?? new FixtureSourceRunVerifier(),
                sourceReducer: $sourceReducer,
            ),
        ]);

        self::assertCount(1, $result->runs);

        return $result->runs[0]->result;
    }

    private function ensurePhpSrcRoot(string $root): void
    {
        if (!is_file($root . '/run-tests.php')) {
            file_put_contents($root . '/run-tests.php', '<?php');
        }
    }

    private function markGitDirty(string $root): void
    {
        $this->ensurePhpSrcRoot($root);
        exec('git -C ' . escapeshellarg($root) . ' init --quiet');
    }

    private function generator(): FixtureGenerator
    {
        return new FixtureGenerator();
    }
}

final readonly class TestFixtureDiscovery implements FixtureDiscovery
{
    public function __construct(
        private FixtureRewriteRunner $runner,
        private FixtureReporter $reporter,
        private FixtureSourceVerifier $sourceVerifier,
        private CandidateCollector $candidates = new CandidateCollector(requireExpectedOutputEvidence: false),
        private ?FixtureSourceReducer $sourceReducer = null,
    ) {}

    public function fixerName(): string
    {
        return 'exception-output';
    }

    public function sourceExtensions(): array
    {
        return ['phpt'];
    }

    public function fixturesDir(string $fixturesRoot): string
    {
        return $fixturesRoot;
    }

    public function reportsDir(string $reportsRoot): string
    {
        return $reportsRoot;
    }

    public function candidates(SourceFile $source): array
    {
        return $this->candidates->collect($source);
    }

    public function reporter(): FixtureReporter
    {
        return $this->reporter;
    }

    public function sourceVerifier(): FixtureSourceVerifier
    {
        return $this->sourceVerifier;
    }

    public function sourceReducer(): ?FixtureSourceReducer
    {
        return $this->sourceReducer;
    }

    public function checkRuntime(ConsoleIo $io): bool
    {
        return true;
    }

    public function requiresPhpTestRuntime(): bool
    {
        return true;
    }

    public function rewriteRunner(PhpSrcRoot $phpTestRuntimeRoot): FixtureRewriteRunner
    {
        return $this->runner;
    }
}

final readonly class FixedFixtureSourceReducer implements FixtureSourceReducer
{
    public function __construct(
        private string $contents,
    ) {}

    public function reduce(FixtureSource $source, FixtureRewriteRunner $runner): string
    {
        return $this->contents;
    }
}

final readonly class CaseNameFixtureSourceReducer implements FixtureSourceReducer
{
    public function reduce(FixtureSource $source, FixtureRewriteRunner $runner): string
    {
        return 'fixture: ' . new FixtureCaseName()->fromFixtureSource($source) . "\n";
    }
}

final readonly class RejectingFixtureSourceVerifier implements FixtureSourceVerifier
{
    public function canSelect(FixtureSource $source, FixtureSourceVerification $verification): bool
    {
        return false;
    }
}

final readonly class ExistingFixtureOnlyVerifier implements FixtureSourceVerifier
{
    public function canSelect(FixtureSource $source, FixtureSourceVerification $verification): bool
    {
        return str_ends_with($source->relativePath, '/old.phpt');
    }
}

final readonly class NoopFixtureRewriteRunner implements FixtureRewriteRunner
{
    public function printFile(string $path): array
    {
        return [
            'changed' => false,
            'failed' => false,
            'output' => (string) file_get_contents($path),
            'failure' => null,
        ];
    }
}

final readonly class ChangedFixtureRewriteRunner implements FixtureRewriteRunner
{
    public function __construct(
        private string $output,
    ) {}

    public function printFile(string $path): array
    {
        return [
            'changed' => true,
            'failed' => false,
            'output' => $this->output,
            'failure' => null,
        ];
    }
}

final readonly class FixtureRunnerStub implements FixtureRewriteRunner, FixtureOriginalRunner
{
    /** @param (\Closure(string): bool)|null $originalPasses */
    /** @param (\Closure(string): ?string)|null $rewrite */
    public function __construct(
        private ?\Closure $originalPasses = null,
        private ?\Closure $rewrite = null,
    ) {}

    public function printFile(string $path): array
    {
        $contents = (string) file_get_contents($path);
        $output = null === $this->rewrite ? null : ($this->rewrite)($path);

        return [
            'changed' => null !== $output,
            'failed' => false,
            'output' => $output ?? $contents,
            'failure' => null,
        ];
    }

    public function runOriginalFile(string $path): array
    {
        return [
            'passed' => null === $this->originalPasses || ($this->originalPasses)($path),
            'failure' => null,
        ];
    }
}
