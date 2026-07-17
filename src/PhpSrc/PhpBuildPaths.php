<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrc;

use function is_executable;
use function is_file;

final readonly class PhpBuildPaths
{
    public function __construct(
        public string $outputDir,
    ) {}

    public static function default(string $toolRoot): self
    {
        return new self($toolRoot . '/bin/php-test-runtime');
    }

    public function phpBinary(): string
    {
        return $this->outputDir . '/php';
    }

    public function cgiBinary(): string
    {
        return $this->outputDir . '/php-cgi';
    }

    public function sourceDir(): string
    {
        return $this->outputDir . '/source';
    }

    public function metadata(): string
    {
        return $this->outputDir . '/build.json';
    }

    public function hasRunnableBinaries(): bool
    {
        return is_file($this->phpBinary()) && is_executable($this->phpBinary())
            && is_file($this->cgiBinary()) && is_executable($this->cgiBinary());
    }
}
