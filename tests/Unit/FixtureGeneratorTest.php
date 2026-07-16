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

        $this->writePhpt($phpSrc, 'a.phpt', 'echo "Caught " . $e->getMessage() . "\n";');
        $this->writePhpt($phpSrc, 'b.phpt', 'echo "*** Caught " . $e->getMessage() . "\n";');

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
            write: false,
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

        $this->writePhptWithStatements($phpSrc, 'a.phpt', [
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
            write: false,
        ));

        self::assertSame(1, $result->candidateFiles);
        self::assertSame(2, $result->candidateWindows);
        self::assertSame(2, $result->candidateFlavours);
        self::assertSame(1, $result->selectedFixtures);
    }

    private function writePhpt(string $root, string $name, string $statement): void
    {
        $this->writePhptWithStatements($root, $name, [$statement]);
    }

    /** @param list<string> $statements */
    private function writePhptWithStatements(string $root, string $name, array $statements): void
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

        file_put_contents($root . '/' . $name, $contents);
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
