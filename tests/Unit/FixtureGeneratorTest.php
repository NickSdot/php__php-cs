<?php

declare(strict_types=1);

namespace Tests\Unit;

use InternalsCS\Console\ConsoleIo;
use InternalsCS\Fixers\ExceptionOutput\Generation\CandidateCollector;
use InternalsCS\Fixers\ExceptionOutput\Generation\FixtureReportWriter;
use InternalsCS\Fixers\ExceptionOutput\Generation\SourceVerifier;
use InternalsCS\Fixture\FixtureDiscovery;
use InternalsCS\Fixture\FixtureGenerationOptions;
use InternalsCS\Fixture\FixtureGenerationResult;
use InternalsCS\Fixture\FixtureGenerator;
use InternalsCS\Fixture\FixtureOriginalRunner;
use InternalsCS\Fixture\FixtureReporter;
use InternalsCS\Fixture\FixtureRewriteRunner;
use InternalsCS\Fixture\FixtureSourceRunVerifier;
use InternalsCS\Fixture\FixtureSourceVerifier;
use InternalsCS\PhpSrc\PhpSrcRoot;
use InternalsCS\SourceFile;
use PHPUnit\Framework\TestCase;

use function basename;
use function bin2hex;
use function escapeshellarg;
use function exec;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function implode;
use function in_array;
use function is_file;
use function mkdir;
use function random_bytes;
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

    public function testDryRunSelectsOneFixtureFileForMultipleFlavoursInTheSameSource(): void
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
        self::assertSame(2, $result->candidateWindows);
        self::assertSame(2, $result->candidateFlavours);
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

    public function testWriteRunReportsPostRefreshHandledFixtureState(): void
    {
        $root = $this->makeTempDir();
        $fixtures = $root . '/fixtures';
        $reports = $root . '/reports';
        $phpSrc = $root . '/php-src';
        mkdir($fixtures);
        mkdir($reports);
        mkdir($phpSrc);
        mkdir($fixtures . '/source');
        file_put_contents($phpSrc . '/run-tests.php', '<?php');

        $sourcePath = $this->writeSourcePhpt($phpSrc, 'source.phpt', 'echo $e->getMessage(), "\n";');
        file_put_contents($fixtures . '/source/old.phpt', (string) file_get_contents($sourcePath));

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
        mkdir($fixtures . '/source');
        file_put_contents($phpSrc . '/run-tests.php', '<?php');

        $sourcePath = $this->writeSourcePhpt($phpSrc, 'source.phpt', 'echo $e->getMessage(), "\n";');
        file_put_contents($fixtures . '/source/old.phpt', (string) file_get_contents($sourcePath));
        file_put_contents($fixtures . '/source/new.phpt', "new\n");
        file_put_contents($fixtures . '/source/ran.diff', "diff\n");

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
        self::assertStringContainsString('stale_pair_kept; source.phpt:8', (string) file_get_contents($reports . '/stats.md'));
    }

    public function testWriteRunSelectsNextSourceWhenFirstRepresentativeDoesNotRun(): void
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

        $this->writeSourcePhpt($phpSrc, 'a.phpt', 'echo $e->getMessage(), "\n";');
        $this->writeSourcePhpt($phpSrc, 'b.phpt', 'echo $e->getMessage(), "\n";');

        $this->generateOne(
            phpSrc: $phpSrc,
            fixtures: $fixtures,
            reports: $reports,
            runner: new SelectiveOriginalRunner(unrunnableBasenames: ['a.phpt']),
            write: true,
            refreshOnly: false,
            runtime: $runtime,
        );

        $oldFixtures = glob($fixtures . '/*/old.phpt');

        self::assertIsArray($oldFixtures);
        self::assertCount(1, $oldFixtures);
        self::assertStringContainsString('b.phpt', (string) file_get_contents($oldFixtures[0]));
        self::assertStringContainsString('b.phpt', (string) file_get_contents($reports . '/fixtures.txt'));
        self::assertStringNotContainsString('a.phpt', (string) file_get_contents($reports . '/fixtures.txt'));
    }

    public function testWriteRunUsesManualFixtureWhenNoSourceRepresentativeRuns(): void
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
        $this->writeManualPhpt($fixtures, 'manual_001', $statement);

        $this->generateOne(
            phpSrc: $phpSrc,
            fixtures: $fixtures,
            reports: $reports,
            runner: new SelectiveOriginalRunner(unrunnableBasenames: ['a.phpt']),
            write: true,
            refreshOnly: false,
            runtime: $runtime,
        );

        self::assertFileExists($fixtures . '/manual_001/old.phpt');
        self::assertStringContainsString('manual_001/old.phpt', (string) file_get_contents($reports . '/fixtures.txt'));
        self::assertStringContainsString('manual_old_only_fixture', (string) file_get_contents($reports . '/stats.md'));
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
            runner: new SelectiveOriginalRunner(unrunnableBasenames: []),
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

    private function writeManualPhpt(string $fixtures, string $case, string $statement): void
    {
        mkdir($fixtures . '/' . $case);

        $contents = <<<PHPT
            --TEST--
            Manual exception-output fixture
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

        file_put_contents($fixtures . '/' . $case . '/old.phpt', $contents);
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
        private CandidateCollector $candidates = new CandidateCollector(),
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

final readonly class SelectiveOriginalRunner implements FixtureRewriteRunner, FixtureOriginalRunner
{
    /** @param list<string> $unrunnableBasenames */
    public function __construct(
        private array $unrunnableBasenames,
    ) {}

    public function printFile(string $path): array
    {
        return [
            'changed' => false,
            'failed' => false,
            'output' => (string) file_get_contents($path),
            'failure' => null,
        ];
    }

    public function runOriginalFile(string $path): array
    {
        return [
            'passed' => !in_array(basename($path), $this->unrunnableBasenames, true),
            'failure' => null,
        ];
    }
}
