<?php

declare(strict_types=1);

namespace InternalsCS;

use function file_get_contents;
use function file_put_contents;
use function mb_strlen;
use function mb_substr;
use function str_starts_with;

final readonly class SourceFile
{
    public string $contents;

    public function __construct(
        public string $path,
        public string $rootDir,
    ) {
        $contents = file_get_contents($path);

        if (false === $contents) {
            throw new \RuntimeException('Cannot read ' . $path);
        }

        $this->contents = $contents;
    }

    public function relativePath(): string
    {
        if (str_starts_with($this->path, $this->rootDir . DIRECTORY_SEPARATOR)) {
            return mb_substr($this->path, mb_strlen($this->rootDir) + 1);
        }

        return $this->path;
    }

    public function restoreOriginal(): void
    {
        if (false === file_put_contents($this->path, $this->contents)) {
            throw new \RuntimeException('Cannot restore ' . $this->path);
        }
    }
}
