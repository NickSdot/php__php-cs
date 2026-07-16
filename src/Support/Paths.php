<?php

declare(strict_types=1);

namespace InternalsCS\Support;

use function file_exists;
use function mb_strlen;
use function mb_substr;
use function preg_match;
use function str_starts_with;

final readonly class Paths
{
    public function absolute(string $path, string $baseDir): string
    {
        if ($this->isAbsolute($path)) {
            return $path;
        }

        return $baseDir . DIRECTORY_SEPARATOR . $path;
    }

    public function isAbsolute(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || 1 === preg_match('/^[A-Za-z]:[\/\\\\]/', $path);
    }

    public function existingRelativeTo(string $path, string $baseDir): string
    {
        if ($this->isAbsolute($path)) {
            return $path;
        }

        $candidate = $baseDir . DIRECTORY_SEPARATOR . $path;

        return file_exists($candidate) ? $candidate : $path;
    }

    public function relative(string $path, string $rootDir): string
    {
        if (str_starts_with($path, $rootDir . DIRECTORY_SEPARATOR)) {
            return mb_substr($path, mb_strlen($rootDir) + 1);
        }

        return $path;
    }
}
