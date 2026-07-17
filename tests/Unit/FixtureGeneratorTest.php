<?php

declare(strict_types=1);

namespace Tests\Unit;

use InternalsCS\Fixture\FixtureGenerationOptions;
use InternalsCS\Fixture\FixtureGenerator;
use InternalsCS\Fixture\FixtureRewriteRunner;
use InternalsCS\PhpSrc\PhpSrcRoot;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Generation\FixtureReportWriter;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Generation\Scanner;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function implode;
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
            allowDirty: false,
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
            allowDirty: false,
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
        file_put_contents($reports . '/queue.txt', "existing queue\n");

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
            allowDirty: false,
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
        self::assertSame("existing queue\n", file_get_contents($reports . '/queue.txt'));
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
            allowDirty: false,
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
        self::assertStringContainsString('Scanned PHPT files: 1', (string) file_get_contents($reports . '/stats.txt'));
        self::assertStringContainsString('source.phpt', (string) file_get_contents($reports . '/queue.txt'));
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
            allowDirty: false,
            sourceDirty: false,
            write: true,
            refreshOnly: false,
        ));

        self::assertStringContainsString('Handled fixture files: 1', (string) file_get_contents($reports . '/stats.txt'));
        self::assertStringContainsString('Queued stale fixture files: 0', (string) file_get_contents($reports . '/stats.txt'));
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
