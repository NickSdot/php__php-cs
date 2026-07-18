<?php

declare(strict_types=1);

namespace Tests\Unit;

use InternalsCS\FixerRunner;
use InternalsCS\PhpSrcTestStyle\FinalNewlineFixer;
use PHPUnit\Framework\TestCase;

use function bin2hex;
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

        self::assertSame(['changed' => 1, 'failed' => 0], $result);
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

        self::assertSame(['changed' => 1, 'failed' => 1], $result);
        self::assertSame("--TEST--\nunsafe final newline\n--FILE--\n<?php\n--EXPECT--", file_get_contents($path));
    }

    private function rootWithRunTests(string $runTests): string
    {
        $root = sys_get_temp_dir() . '/final-newline-fixer-' . bin2hex(random_bytes(6));
        mkdir($root);
        file_put_contents($root . '/run-tests.php', $runTests);

        return $root;
    }

    /** @return array{changed: int, failed: int} */
    private function runFinalNewlineFixer(string $root, string $path): array
    {
        ob_start();

        try {
            return new FixerRunner($root, [FinalNewlineFixer::class])->run([$path], check: false);
        } finally {
            ob_end_clean();
        }
    }
}
