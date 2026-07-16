<?php

declare(strict_types=1);

namespace Tests\Unit;

use InternalsCS\SourceFinder;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function realpath;
use function sys_get_temp_dir;

final class SourceFinderTest extends TestCase
{
    public function testFindsFilesByConfiguredExtension(): void
    {
        $root = $this->tempDir();
        mkdir($root . '/nested');
        file_put_contents($root . '/a.phpt', '');
        file_put_contents($root . '/b.php', '');
        file_put_contents($root . '/nested/c.phpt', '');

        $files = new SourceFinder()->find(
            rootDir: $root,
            scanPaths: [],
            excludedRoots: [],
            extensions: ['phpt'],
        );

        self::assertSame([
            realpath($root . '/a.phpt'),
            realpath($root . '/nested/c.phpt'),
        ], $files);
    }

    public function testFindsAllFilesWhenExtensionsAreEmpty(): void
    {
        $root = $this->tempDir();
        file_put_contents($root . '/a.phpt', '');
        file_put_contents($root . '/b.txt', '');

        $files = new SourceFinder()->find(
            rootDir: $root,
            scanPaths: [],
            excludedRoots: [],
        );

        self::assertSame([
            realpath($root . '/a.phpt'),
            realpath($root . '/b.txt'),
        ], $files);
    }

    public function testExcludesRoots(): void
    {
        $root = $this->tempDir();
        mkdir($root . '/fixtures');
        file_put_contents($root . '/a.phpt', '');
        file_put_contents($root . '/fixtures/old.phpt', '');

        $files = new SourceFinder()->find(
            rootDir: $root,
            scanPaths: [],
            excludedRoots: [$root . '/fixtures'],
            extensions: ['phpt'],
        );

        self::assertSame([realpath($root . '/a.phpt')], $files);
    }

    private function tempDir(): string
    {
        $root = sys_get_temp_dir() . '/source-finder-' . bin2hex(random_bytes(6));
        mkdir($root);

        return $root;
    }
}
