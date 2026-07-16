<?php

declare(strict_types=1);

namespace Tests\Unit;

use InternalsCS\FixerRunner;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

final class FixerRunnerTest extends TestCase
{
    public function testCollectFilesFindsFilesRecursivelyAndSortsThem(): void
    {
        $root = $this->makeTempDir();
        mkdir($root . '/nested');
        file_put_contents($root . '/b.phpt', '--TEST--');
        file_put_contents($root . '/nested/a.phpt', '--TEST--');
        file_put_contents($root . '/nested/source.php', '<?php');

        $runner = new FixerRunner($root, []);

        self::assertSame([
            $root . '/b.phpt',
            $root . '/nested/a.phpt',
            $root . '/nested/source.php',
        ], $runner->collectFiles([]));
    }

    public function testCollectFilesRejectsMissingPath(): void
    {
        $root = $this->makeTempDir();
        $runner = new FixerRunner($root, []);

        $this->expectException(\InvalidArgumentException::class);
        $runner->collectFiles(['missing.phpt']);
    }

    private function makeTempDir(): string
    {
        $root = sys_get_temp_dir() . '/runner-' . bin2hex(random_bytes(6));
        mkdir($root);

        return $root;
    }
}
