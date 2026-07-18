<?php

declare(strict_types=1);

namespace Tests\Unit;

use InternalsCS\Fixture\FixtureGenerationOptions;
use InternalsCS\Fixture\FixtureGenerator;
use InternalsCS\Fixture\FixtureOriginalRunner;
use InternalsCS\Fixture\FixtureRewriteRunner;
use InternalsCS\PhpSrc\PhpSrcRoot;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Generation\FixtureReportWriter;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Generation\Scanner;
use PHPUnit\Framework\TestCase;

use function basename;
use function bin2hex;
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

        $result = $this->generator()->generate(new FixtureGenerationOptions(
            sourceRoot: PhpSrcRoot::fromPath($phpSrc)->path,
            fixturesDir: $fixtures,
            reportsDir: $fixtures,
            paths: [],
            excludedRoots: [
                $fixtures,
            ],
            extensions: ['phpt'],
            runner: new NoopFixtureRewriteRunner(),
            sourceDirty: false,
            write: false,
            refreshOnly: false,
        ));

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

        $result = $this->generator()->generate(new FixtureGenerationOptions(
            sourceRoot: PhpSrcRoot::fromPath($phpSrc)->path,
            fixturesDir: $fixtures,
            reportsDir: $fixtures,
            paths: [],
            excludedRoots: [
                $fixtures,
            ],
            extensions: ['phpt'],
            runner: new NoopFixtureRewriteRunner(),
            sourceDirty: false,
            write: false,
            refreshOnly: false,
        ));

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

        $result = $this->generator()->generate(new FixtureGenerationOptions(
            sourceRoot: $phpSrc,
            fixturesDir: $fixtures,
            reportsDir: $reports,
            paths: [],
            excludedRoots: [
                $fixtures,
            ],
            extensions: ['phpt'],
            runner: new ChangedFixtureRewriteRunner("new\n"),
            sourceDirty: true,
            write: true,
            refreshOnly: false,
        ));

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

        $result = $this->generator()->generate(new FixtureGenerationOptions(
            sourceRoot: $phpSrc,
            fixturesDir: $fixtures,
            reportsDir: $reports,
            paths: [],
            excludedRoots: [
                $fixtures,
            ],
            extensions: ['phpt'],
            runner: new ChangedFixtureRewriteRunner("new\n"),
            sourceDirty: false,
            write: true,
            refreshOnly: true,
        ));

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

        $this->generator()->generate(new FixtureGenerationOptions(
            sourceRoot: $phpSrc,
            fixturesDir: $fixtures,
            reportsDir: $reports,
            paths: [],
            excludedRoots: [
                $fixtures,
            ],
            extensions: ['phpt'],
            runner: new ChangedFixtureRewriteRunner("new\n"),
            sourceDirty: false,
            write: true,
            refreshOnly: false,
        ));

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

        $this->generator()->generate(new FixtureGenerationOptions(
            sourceRoot: PhpSrcRoot::fromPath($phpSrc)->path,
            fixturesDir: $fixtures,
            reportsDir: $reports,
            paths: [],
            excludedRoots: [
                $fixtures,
            ],
            extensions: ['phpt'],
            runner: new NoopFixtureRewriteRunner(),
            sourceDirty: false,
            write: true,
            refreshOnly: false,
        ));

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

        $this->generator()->generate(new FixtureGenerationOptions(
            sourceRoot: $phpSrc,
            fixturesDir: $fixtures,
            reportsDir: $reports,
            paths: [],
            excludedRoots: [
                $fixtures,
            ],
            extensions: ['phpt'],
            runner: new SelectiveOriginalRunner(unrunnableBasenames: ['a.phpt']),
            sourceDirty: false,
            write: true,
            refreshOnly: false,
            rewriteRoot: $runtime,
        ));

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

        $this->generator()->generate(new FixtureGenerationOptions(
            sourceRoot: $phpSrc,
            fixturesDir: $fixtures,
            reportsDir: $reports,
            paths: [],
            excludedRoots: [
                $fixtures,
            ],
            extensions: ['phpt'],
            runner: new SelectiveOriginalRunner(unrunnableBasenames: ['a.phpt']),
            sourceDirty: false,
            write: true,
            refreshOnly: false,
            rewriteRoot: $runtime,
        ));

        self::assertFileExists($fixtures . '/manual_001/old.phpt');
        self::assertStringContainsString('manual_001/old.phpt', (string) file_get_contents($reports . '/fixtures.txt'));
        self::assertStringContainsString('manual_old_only_fixture', (string) file_get_contents($reports . '/stats.md'));
        self::assertStringNotContainsString('no_selected_runnable_dir', (string) file_get_contents($reports . '/stats.md'));
    }

    private function writeSourcePhpt(string $root, string $name, string $statement): string
    {
        return $this->writeSourcePhptWithStatements($root, $name, [$statement]);
    }

    /** @param list<string> $statements */
    private function writeSourcePhptWithStatements(string $root, string $name, array $statements): string
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

    private function generator(): FixtureGenerator
    {
        return new FixtureGenerator(
            scanner: new Scanner(),
            reports: new FixtureReportWriter(),
        );
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
