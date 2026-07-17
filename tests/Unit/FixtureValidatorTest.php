<?php

declare(strict_types=1);

namespace Tests\Unit;

use InternalsCS\Fixture\FixtureRewriteRunner;
use InternalsCS\Fixture\FixtureValidationOptions;
use InternalsCS\Fixture\FixtureValidator;
use InternalsCS\Support\UnifiedDiff;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

final class FixtureValidatorTest extends TestCase
{
    public function testNewFixtureWithoutOldFixtureFailsStructurally(): void
    {
        $fixtures = $this->fixturesDir();
        mkdir($fixtures . '/broken', recursive: true);
        file_put_contents($fixtures . '/broken/new.phpt', '--TEST--');
        file_put_contents($fixtures . '/broken/ran.diff', 'diff');

        $result = $this->validate($fixtures, update: false);

        self::assertSame(['broken: missing old.phpt'], $result->failures);
    }

    public function testNewFixtureWithoutDiffFailsStructurally(): void
    {
        $fixtures = $this->fixturesDir();
        mkdir($fixtures . '/broken', recursive: true);
        file_put_contents($fixtures . '/broken/old.phpt', "--TEST--\n--FILE--\n<?php\n--EXPECT--\n");
        file_put_contents($fixtures . '/broken/new.phpt', '--TEST--');

        $result = $this->validate($fixtures, update: false);

        self::assertSame(['broken: new.phpt exists but ran.diff is missing'], $result->failures);
    }

    public function testRefreshDoesNotDeleteGeneratedPairWhenOldFixtureIsMissing(): void
    {
        $fixtures = $this->fixturesDir();
        mkdir($fixtures . '/stale', recursive: true);
        file_put_contents($fixtures . '/stale/new.phpt', '--TEST--');
        file_put_contents($fixtures . '/stale/ran.diff', 'diff');

        $result = $this->validate($fixtures, update: true, refreshPairs: true);

        self::assertSame(['stale: missing old.phpt'], $result->failures);
        self::assertSame(0, $result->deletedPairs);
        self::assertFileExists($fixtures . '/stale/new.phpt');
        self::assertFileExists($fixtures . '/stale/ran.diff');
    }

    public function testValidationIgnoresDirectoriesWithoutFixtureFiles(): void
    {
        $fixtures = $this->fixturesDir();
        mkdir($fixtures . '/noise', recursive: true);
        file_put_contents($fixtures . '/noise/old.tmp', '');

        $result = $this->validate($fixtures, update: false);

        self::assertSame(0, $result->fixtures);
        self::assertSame([], $result->failures);
    }

    public function testRefreshUpdatesStaleDiffWhenNewFixtureIsCurrent(): void
    {
        $fixtures = $this->fixturesDir();
        mkdir($fixtures . '/stale', recursive: true);
        file_put_contents($fixtures . '/stale/old.phpt', "old\n");
        file_put_contents($fixtures . '/stale/new.phpt', "new\n");
        file_put_contents($fixtures . '/stale/ran.diff', "stale\n");

        $result = $this->validate($fixtures, runner: $this->runner("new\n"), update: true);

        self::assertSame([], $result->failures);
        self::assertSame(1, $result->updated);
        self::assertSame(
            new UnifiedDiff()->betweenFiles(
                $fixtures . '/stale/old.phpt',
                $fixtures . '/stale/new.phpt',
                'old.phpt',
                'new.phpt',
            ),
            file_get_contents($fixtures . '/stale/ran.diff'),
        );
    }

    public function testRefreshOverwritesExistingNewFixtureWhenVerifiedRewriteDiffers(): void
    {
        $fixtures = $this->fixturesDir();
        mkdir($fixtures . '/drifted', recursive: true);
        file_put_contents($fixtures . '/drifted/old.phpt', "old\n");
        file_put_contents($fixtures . '/drifted/new.phpt', "expected\n");
        file_put_contents($fixtures . '/drifted/ran.diff', "diff\n");

        $result = $this->validate(
            fixtures: $fixtures,
            runner: $this->runner("actual\n"),
            update: true,
            refreshPairs: true,
        );

        self::assertSame([], $result->failures);
        self::assertSame(1, $result->updated);
        self::assertSame("actual\n", file_get_contents($fixtures . '/drifted/new.phpt'));
        self::assertSame(
            new UnifiedDiff()->betweenFiles(
                $fixtures . '/drifted/old.phpt',
                $fixtures . '/drifted/new.phpt',
                'old.phpt',
                'new.phpt',
            ),
            file_get_contents($fixtures . '/drifted/ran.diff'),
        );
    }

    public function testRefreshKeepsGeneratedPairWhenFixerNoLongerChangesOldFixture(): void
    {
        $fixtures = $this->fixturesDir();
        mkdir($fixtures . '/stale', recursive: true);
        file_put_contents($fixtures . '/stale/old.phpt', "old\n");
        file_put_contents($fixtures . '/stale/new.phpt', "new\n");
        file_put_contents($fixtures . '/stale/ran.diff', "diff\n");

        $result = $this->validate(
            fixtures: $fixtures,
            runner: $this->runner("old\n", changed: false),
            update: true,
            refreshPairs: true,
        );

        self::assertSame([], $result->failures);
        self::assertSame(1, $result->stalePairs);
        self::assertSame(0, $result->oldOnly);
        self::assertSame(0, $result->deletedPairs);
        self::assertFileExists($fixtures . '/stale/old.phpt');
        self::assertFileExists($fixtures . '/stale/new.phpt');
        self::assertFileExists($fixtures . '/stale/ran.diff');
    }

    public function testRefreshPreservesGeneratedPairWhenRewriteVerificationFails(): void
    {
        $fixtures = $this->fixturesDir();
        mkdir($fixtures . '/needs-extension', recursive: true);
        file_put_contents($fixtures . '/needs-extension/old.phpt', "old\n");
        file_put_contents($fixtures . '/needs-extension/new.phpt', "new\n");
        file_put_contents($fixtures . '/needs-extension/ran.diff', "diff\n");

        $result = $this->validate(
            fixtures: $fixtures,
            runner: $this->runner(
                output: "old\n",
                changed: false,
                failed: true,
                failure: 'original test did not pass',
            ),
            update: true,
            refreshPairs: true,
        );

        self::assertSame(
            ['needs-extension: rewrite failed verification: original test did not pass'],
            $result->failures,
        );
        self::assertSame(0, $result->deletedPairs);
        self::assertFileExists($fixtures . '/needs-extension/new.phpt');
        self::assertFileExists($fixtures . '/needs-extension/ran.diff');
    }

    private function validate(
        string $fixtures,
        ?FixtureRewriteRunner $runner = null,
        bool $update = false,
        bool $refreshPairs = false,
    ): \InternalsCS\Fixture\FixtureValidationResult {
        return new FixtureValidator()->validate(new FixtureValidationOptions(
            fixturesDir: $fixtures,
            cases: [],
            runner: $runner ?? $this->runner('unused'),
            update: $update,
            failFast: false,
            refreshPairs: $refreshPairs,
        ));
    }

    private function runner(
        string $output,
        bool $changed = true,
        bool $failed = false,
        ?string $failure = null,
    ): FixtureRewriteRunner {
        return new StaticFixtureRewriteRunner(
            output: $output,
            changed: $changed,
            failed: $failed,
            failure: $failure,
        );
    }

    private function fixturesDir(): string
    {
        $root = sys_get_temp_dir() . '/fixture-validator-' . bin2hex(random_bytes(6));
        mkdir($root);
        mkdir($root . '/fixtures');

        return $root . '/fixtures';
    }
}

final readonly class StaticFixtureRewriteRunner implements FixtureRewriteRunner
{
    public function __construct(
        private string $output,
        private bool $changed,
        private bool $failed,
        private ?string $failure,
    ) {}

    public function printFile(string $path): array
    {
        return [
            'changed' => $this->changed,
            'failed' => $this->failed,
            'output' => $this->output,
            'failure' => $this->failure,
        ];
    }
}
