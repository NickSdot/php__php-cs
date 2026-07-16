<?php

declare(strict_types=1);

namespace InternalsCS\Support;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function is_readable;
use function mkdir;

final readonly class FileSystem
{
    public function ensureDirectory(string $dir, string $label = 'directory'): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (mkdir($dir, 0o777, true) || is_dir($dir)) {
            return;
        }

        throw new \RuntimeException('Cannot create ' . $label . ': ' . $dir);
    }

    public function read(string $path, string $label = 'file'): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('Cannot read ' . $label . ': ' . $path);
        }

        $contents = file_get_contents($path);

        if (false !== $contents) {
            return $contents;
        }

        throw new \RuntimeException('Cannot read ' . $label . ': ' . $path);
    }

    public function write(string $path, string $contents, string $label = 'file'): void
    {
        $dir = dirname($path);

        if ('' !== $dir && '.' !== $dir) {
            $this->ensureDirectory($dir, $label . ' directory');
        }

        if (false !== file_put_contents($path, $contents)) {
            return;
        }

        throw new \RuntimeException('Cannot write ' . $label . ': ' . $path);
    }
}
