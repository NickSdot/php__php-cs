<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use InternalsCS\Support\FileSystem;

use function dirname;
use function is_file;
use function realpath;

final readonly class FixtureSourceContext
{
    public function __construct(
        private FileSystem $files = new FileSystem(),
    ) {}

    /**
     * @template T
     *
     * @param callable(string): T $run
     * @return T
     */
    public function run(string $sourcePath, string $rewritePath, callable $run): mixed
    {
        $hadRewriteTarget = is_file($rewritePath);
        $original = $hadRewriteTarget ? $this->files->read($rewritePath, 'rewrite target') : null;

        $this->files->ensureDirectory(dirname($rewritePath), 'rewrite target directory');
        $this->files->write($rewritePath, $this->files->read($sourcePath, 'source file'), 'rewrite target');

        try {
            return $run($this->realPath($rewritePath));
        } finally {
            if ($hadRewriteTarget) {
                $this->files->write($rewritePath, (string) $original, 'rewrite target');
            } else {
                $this->files->deleteFileIfExists($rewritePath, 'rewrite target');
            }
        }
    }

    private function realPath(string $path): string
    {
        $realPath = realpath($path);

        return false === $realPath ? $path : $realPath;
    }
}
