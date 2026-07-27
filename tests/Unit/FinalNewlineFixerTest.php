<?php

declare(strict_types=1);

namespace Tests\Unit;

use InternalsCS\FixRunEntry;
use InternalsCS\FixRunResult;
use InternalsCS\FixerRunner;
use InternalsCS\Fixers\FinalNewline\FinalNewlineFixer;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function chmod;
use function escapeshellarg;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function ob_end_clean;
use function ob_start;
use function random_bytes;
use function str_replace;
use function sys_get_temp_dir;

final class FinalNewlineFixerTest extends TestCase
{
    public function testAddsMissingFinalNewlineAfterVerification(): void
    {
        $root = $this->rootWithRunTests(<<<'PHP'
            $status = 'PASSED';
            PHP);
        $path = $root . '/missing.phpt';

        file_put_contents($path, "--TEST--\nmissing final newline\n--FILE--\n<?php\n--EXPECT--\nold");

        $result = $this->runFinalNewlineFixer($root, $path);

        self::assertSame(1, $result->changed());
        self::assertSame(0, $result->skipped());
        self::assertSame(1, $result->fixed());
        self::assertSame("--TEST--\nmissing final newline\n--FILE--\n<?php\n--EXPECT--\nold\n", file_get_contents($path));
    }

    public function testRestoresOriginalWhenVerifiedRewriteFails(): void
    {
        $root = $this->rootWithRunTests(<<<'PHP'
            $contents = \file_get_contents($target);
            $status = \str_ends_with($contents, "\n") ? 'FAILED' : 'PASSED';
            PHP);
        $path = $root . '/unsafe.phpt';

        file_put_contents($path, "--TEST--\nunsafe final newline\n--FILE--\n<?php\n--EXPECT--");

        $result = $this->runFinalNewlineFixer($root, $path);

        self::assertSame(1, $result->changed());
        self::assertSame(1, $result->skipped());
        self::assertSame("--TEST--\nunsafe final newline\n--FILE--\n<?php\n--EXPECT--", file_get_contents($path));
    }

    public function testReportsProgressThroughCallback(): void
    {
        $root = $this->rootWithRunTests(<<<'PHP'
            $status = 'PASSED';
            PHP);
        $path = $root . '/missing.phpt';
        $progress = [];

        file_put_contents($path, "--TEST--\nmissing final newline\n--FILE--\n<?php\n--EXPECT--\nold");

        new FixerRunner($root, [FinalNewlineFixer::class])->run(
            files: [$path],
            check: false,
            onEntry: static function (FixRunEntry $entry) use (&$progress): void {
                $progress[] = $entry->consoleLine();
            },
        );

        self::assertSame(['missing.phpt: final-newline (FILE line 6) fixed'], $progress);
    }

    public function testValidatesMultipleFilesWithOneHarnessInvocationPerPhase(): void
    {
        $root = $this->rootWithRunTests(<<<'PHP'
            $status = 'PASSED';
            PHP);
        $first = $root . '/first.phpt';
        $second = $root . '/second.phpt';

        file_put_contents($first, "--TEST--\nfirst\n--FILE--\n<?php\n--EXPECT--\nfirst");
        file_put_contents($second, "--TEST--\nsecond\n--FILE--\n<?php\n--EXPECT--\nsecond");

        $result = new FixerRunner($root, [FinalNewlineFixer::class])->run([$first, $second], check: false);

        self::assertSame(2, $result->fixed());
        self::assertSame('2', file_get_contents($root . '/invocations'));
    }

    public function testKeepsAnIndependentDecisionForEachFileInTheBatch(): void
    {
        $root = $this->rootWithRunTests(<<<'PHP'
            $contents = \file_get_contents($target);
            $unsafeRewrite = \str_contains($target, 'unsafe') && \str_ends_with($contents, "\n");
            $status = $unsafeRewrite ? 'FAILED' : 'PASSED';
            PHP);
        $safe = $root . '/safe.phpt';
        $unsafe = $root . '/unsafe.phpt';
        $safeOriginal = "--TEST--\nsafe\n--FILE--\n<?php\n--EXPECT--\nsafe";
        $unsafeOriginal = "--TEST--\nunsafe\n--FILE--\n<?php\n--EXPECT--\nunsafe";

        file_put_contents($safe, $safeOriginal);
        file_put_contents($unsafe, $unsafeOriginal);

        $result = new FixerRunner($root, [FinalNewlineFixer::class])->run([$safe, $unsafe], check: false);

        self::assertSame(1, $result->fixed());
        self::assertSame(1, $result->skipped());
        self::assertSame($safeOriginal . "\n", file_get_contents($safe));
        self::assertSame($unsafeOriginal, file_get_contents($unsafe));
        self::assertSame('2', file_get_contents($root . '/invocations'));
    }

    private function rootWithRunTests(string $test): string
    {
        $root = sys_get_temp_dir() . '/final-newline-fixer-' . bin2hex(random_bytes(6));
        mkdir($root);
        mkdir($root . '/sapi/cli', recursive: true);
        $runTests = <<<'PHP'
            <?php

            $listPath = null;
            $resultPath = null;

            for ($i = 1; $i < count($argv); $i++) {
                if ('-r' === $argv[$i]) {
                    $listPath = $argv[++$i];
                } elseif ('-W' === $argv[$i]) {
                    $resultPath = $argv[++$i];
                }
            }

            $invocationsPath = __DIR__ . '/invocations';
            $invocations = is_file($invocationsPath) ? (int) file_get_contents($invocationsPath) : 0;
            file_put_contents($invocationsPath, (string) ($invocations + 1));

            $results = [];
            $failed = false;

            foreach (file($listPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $target) {
                {{TEST}}

                $results[] = "$status\t$target";
                $display = match ($status) {
                    'PASSED' => 'PASS',
                    'FAILED' => 'FAIL',
                    default => $status,
                };
                echo "$display fixture [$target]\n";
                $failed = $failed || 'PASSED' !== $status;
            }

            file_put_contents($resultPath, implode("\n", $results) . "\n");
            exit($failed ? 1 : 0);
            PHP;

        file_put_contents($root . '/run-tests.php', str_replace('{{TEST}}', $test, $runTests));
        file_put_contents($root . '/sapi/cli/php', "#!/bin/sh\nexec " . escapeshellarg(PHP_BINARY) . " \"$@\"\n");
        chmod($root . '/sapi/cli/php', 0o755);

        return $root;
    }

    private function runFinalNewlineFixer(string $root, string $path): FixRunResult
    {
        ob_start();

        try {
            return new FixerRunner($root, [FinalNewlineFixer::class])->run([$path], check: false);
        } finally {
            ob_end_clean();
        }
    }
}
