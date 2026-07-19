<?php

declare(strict_types=1);

namespace InternalsCS;

use InternalsCS\Support\Paths;

use function array_any;
use function array_unique;
use function array_values;
use function is_dir;
use function is_file;
use function mb_ltrim;
use function mb_strtolower;
use function pathinfo;
use function realpath;
use function sort;
use function str_starts_with;

final readonly class SourceFinder
{
    public function __construct(
        private Paths $paths = new Paths(),
    ) {}

    /**
     * @param list<string> $scanPaths
     * @param list<string> $excludedRoots
     * @param list<string> $extensions
     * @return list<string>
     */
    public function find(string $rootDir, array $scanPaths, array $excludedRoots, array $extensions = []): array
    {
        $scanPaths = [] === $scanPaths ? [$rootDir] : $scanPaths;
        $extensions = $this->normaliseExtensions($extensions);
        $files = [];

        foreach ($scanPaths as $path) {
            $path = $this->paths->absolute($path, $rootDir);

            if (is_file($path)) {

                $realPath = realpath($path);
                $realPath = false === $realPath ? $path : $realPath;

                if ($this->hasAllowedExtension($realPath, $extensions) && !$this->isExcluded($realPath, $excludedRoots)) {
                    $files[] = $realPath;
                }

                continue;
            }

            if (!is_dir($path)) {
                throw new \InvalidArgumentException('Path does not exist: ' . $path);
            }

            foreach ($this->directoryFiles($path, $excludedRoots, $extensions) as $file) {
                $files[] = $file;
            }
        }

        sort($files);

        return array_values(array_unique($files));
    }

    /**
     * @param list<string> $extensions
     * @return list<string>
     */
    public function normaliseExtensions(array $extensions): array
    {
        $normalised = [];

        foreach ($extensions as $extension) {
            $normalised[$this->extensionKey($extension)] = $this->extensionKey($extension);
        }

        sort($normalised);

        return $normalised;
    }

    /**
     * @param list<string> $excludedRoots
     * @param list<string> $extensions
     * @return list<string>
     */
    private function directoryFiles(string $path, array $excludedRoots, array $extensions): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            if (!$file->isFile()) {
                continue;
            }

            $pathName = $file->getPathname();
            $realPath = realpath($pathName);
            $realPath = false === $realPath ? $pathName : $realPath;

            if (!$this->hasAllowedExtension($realPath, $extensions)) {
                continue;
            }

            if ($this->isExcluded($realPath, $excludedRoots)) {
                continue;
            }

            $files[] = $realPath;
        }

        return $files;
    }

    /** @param list<string> $excludedRoots */
    private function isExcluded(string $path, array $excludedRoots): bool
    {
        foreach ($excludedRoots as $root) {
            $realRoot = realpath($root);
            $realRoot = false === $realRoot ? $root : $realRoot;

            if ($path === $realRoot || str_starts_with($path, $realRoot . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $extensions */
    private function hasAllowedExtension(string $path, array $extensions): bool
    {
        if ([] === $extensions) {
            return true;
        }

        return array_any(
            $extensions,
            fn(string $allowed): bool => $this->extensionKey(pathinfo($path, PATHINFO_EXTENSION)) === $allowed,
        );
    }

    private function extensionKey(string $extension): string
    {
        return mb_strtolower(mb_ltrim($extension, '.'));
    }
}
