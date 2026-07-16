<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrc;

use function is_file;
use function realpath;

final readonly class PhpSrcRoot
{
    private function __construct(
        public string $path,
    ) {}

    public static function fromPath(string $path): self
    {
        $realPath = realpath($path) ?: $path;

        if (!is_file($realPath . DIRECTORY_SEPARATOR . 'run-tests.php')) {
            throw new \InvalidArgumentException(
                'php-src root does not contain run-tests.php: ' . $realPath,
            );
        }

        return new self($realPath);
    }
}
