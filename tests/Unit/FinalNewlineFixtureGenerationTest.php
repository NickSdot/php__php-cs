<?php

declare(strict_types=1);

namespace Tests\Unit;

use InternalsCS\Console\ConsoleIo;
use InternalsCS\Fixers\FinalNewline\Generation\CandidateCollector;
use InternalsCS\Fixture\FixtureDiscovery;
use InternalsCS\Fixture\FixtureGenerationOptions;
use InternalsCS\Fixture\FixtureGenerator;
use InternalsCS\Fixture\FixtureReporter;
use InternalsCS\Fixture\FixtureRewriteRunner;
use InternalsCS\Fixture\FixtureSource;
use InternalsCS\Fixture\FixtureSourceVerification;
use InternalsCS\Fixture\FixtureSourceVerifier;
use InternalsCS\PhpSrc\PhpSrcRoot;
use InternalsCS\SourceFile;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_put_contents;
use function is_file;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

final class FinalNewlineFixtureGenerationTest extends TestCase
{
    public function testCollectorFindsMissingAndExtraFinalNewlines(): void
    {
        $root = $this->makeTempDir();
        $missing = $root . '/missing.phpt';
        $extra = $root . '/extra.phpt';
        $normalised = $root . '/normalised.phpt';

        file_put_contents($missing, "--TEST--\nmissing\n--FILE--\n<?php");
        file_put_contents($extra, "--TEST--\nextra\n--FILE--\n<?php\n\n");
        file_put_contents($normalised, "--TEST--\nnormalised\n--FILE--\n<?php\n");

        $candidates = [
            ...new CandidateCollector()->collect(new SourceFile($missing, $root)),
            ...new CandidateCollector()->collect(new SourceFile($extra, $root)),
            ...new CandidateCollector()->collect(new SourceFile($normalised, $root)),
        ];

        self::assertCount(2, $candidates);
        self::assertSame('missing-final-newline', $candidates[0]->fixtureKey);
        self::assertSame('extra-final-newlines', $candidates[1]->fixtureKey);
    }

    public function testGeneratorWritesFinalNewlineFixturePairs(): void
    {
        $root = $this->makeTempDir();
        $fixtures = $root . '/fixtures';
        $reports = $root . '/reports';
        $phpSrc = $root . '/php-src';
        mkdir($fixtures);
        mkdir($reports);
        mkdir($phpSrc);

        file_put_contents($phpSrc . '/run-tests.php', '<?php');
        file_put_contents($phpSrc . '/missing.phpt', "--TEST--\nmissing\n--FILE--\n<?php");

        $result = new FixtureGenerator()->generate(new FixtureGenerationOptions(
            phpSrcRoot: PhpSrcRoot::fromPath($phpSrc),
            phpTestRuntimeRoot: PhpSrcRoot::fromPath($phpSrc),
            fixturesRoot: $fixtures,
            reportsRoot: $reports,
            paths: [],
            allowDirty: true,
            write: true,
            refreshOnly: false,
        ), [
            new FinalNewlineTestDiscovery(new FinalNewlineChangedFixtureRewriteRunner("--TEST--\nmissing\n--FILE--\n<?php\n")),
        ]);

        self::assertCount(1, $result->runs);
        self::assertSame(1, $result->runs[0]->result->createdOld);
        self::assertSame(1, $result->runs[0]->result->updatedPairs);
        self::assertTrue(is_file($fixtures . '/missing/old.phpt'));
        self::assertTrue(is_file($fixtures . '/missing/new.phpt'));
        self::assertTrue(is_file($fixtures . '/missing/ran.diff'));
    }

    private function makeTempDir(): string
    {
        $root = sys_get_temp_dir() . '/final-newline-fixtures-' . bin2hex(random_bytes(6));
        mkdir($root);

        return $root;
    }
}

final readonly class FinalNewlineTestDiscovery implements FixtureDiscovery
{
    public function __construct(
        private FixtureRewriteRunner $runner,
        private CandidateCollector $candidates = new CandidateCollector(),
    ) {}

    public function fixerName(): string
    {
        return 'final-newline';
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

    public function reporter(): ?FixtureReporter
    {
        return null;
    }

    public function sourceVerifier(): FixtureSourceVerifier
    {
        return new FinalNewlineTestSourceVerifier();
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

final readonly class FinalNewlineTestSourceVerifier implements FixtureSourceVerifier
{
    public function canSelect(
        FixtureSource $source,
        FixtureSourceVerification $verification,
    ): bool {
        return true;
    }
}

final readonly class FinalNewlineChangedFixtureRewriteRunner implements FixtureRewriteRunner
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
