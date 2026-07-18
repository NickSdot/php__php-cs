<?php

declare(strict_types=1);

namespace Tests\Unit;

use InternalsCS\Support\FileSystem;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

final class FileSystemTest extends TestCase
{
    public function testWritesAndReadsFileCreatingParentDirectory(): void
    {
        $root = $this->tempDir();
        $path = $root . '/nested/file.txt';

        $files = new FileSystem();
        $files->write($path, "contents\n", 'fixture');

        self::assertDirectoryExists($root . '/nested');
        self::assertSame("contents\n", $files->read($path, 'fixture'));
    }

    public function testReadFailureMentionsLabelAndPath(): void
    {
        $path = sys_get_temp_dir() . '/php-src-cs-missing-' . bin2hex(random_bytes(8));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot read fixture: ' . $path);

        new FileSystem()->read($path, 'fixture');
    }

    private function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/php-src-cs-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($dir));

        return $dir;
    }
}
