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
use function sys_get_temp_dir;

final class FinalNewlineFixerTest extends TestCase
{
    public function testAddsMissingFinalNewlineAfterVerification(): void
    {
        $root = $this->rootWithRunTests(<<<'PHP'
            <?php
            echo "PASS ", $argv[\array_key_last($argv)], "\n";
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
            <?php
            $target = $argv[\array_key_last($argv)];
            $contents = \file_get_contents($target);

            if (\str_ends_with($contents, "\n")) {
                echo "FAIL $target\n";
                exit(1);
            }

            echo "PASS $target\n";
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
            <?php
            echo "PASS ", $argv[\array_key_last($argv)], "\n";
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

    private function rootWithRunTests(string $runTests): string
    {
        $root = sys_get_temp_dir() . '/final-newline-fixer-' . bin2hex(random_bytes(6));
        mkdir($root);
        mkdir($root . '/sapi/cli', recursive: true);
        file_put_contents($root . '/run-tests.php', $runTests);
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
