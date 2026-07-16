<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrc;

use function array_filter;
use function array_unique;
use function array_values;
use function dirname;
use function explode;
use function glob;
use function is_dir;
use function is_string;

final readonly class PkgConfigPathResolver
{
    /** @param list<string> $roots */
    public function __construct(
        private array $roots = [],
    ) {}

    /**
     * @param array<string, string> $environment
     * @param list<string> $packages
     *
     * @return list<string>
     */
    public function resolve(array $environment, array $packages): array
    {
        $paths = [];

        $this->appendDelimited($paths, $environment['PKG_CONFIG_PATH'] ?? '');

        foreach ($this->roots($environment) as $root) {
            $this->appendPackageDirs($paths, $root, $packages);
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param array<string, string> $environment
     *
     * @return list<string>
     */
    private function roots(array $environment): array
    {
        $roots = $this->roots;

        foreach (['HOMEBREW_PREFIX', 'CONDA_PREFIX', 'PREFIX'] as $name) {
            $root = $environment[$name] ?? '';

            if ('' !== $root) {
                $roots[] = $root;
            }
        }

        foreach (['/opt/homebrew', '/opt/local', '/usr/local', '/usr'] as $root) {
            if (is_dir($root)) {
                $roots[] = $root;
            }
        }

        return array_filter($roots, is_string(...))
                |> array_unique(...)
                |> array_values(...);
    }

    /**
     * @param list<string> $paths
     * @param list<string> $packages
     */
    private function appendPackageDirs(array &$paths, string $root, array $packages): void
    {
        foreach ($packages as $package) {
            foreach ($this->packagePatterns($root, $package) as $pattern) {
                $matches = glob($pattern);

                if (false === $matches) {
                    continue;
                }

                foreach ($matches as $match) {
                    $dir = dirname($match);

                    if (is_dir($dir)) {
                        $paths[] = $dir;
                    }
                }
            }
        }
    }

    /** @return list<string> */
    private function packagePatterns(string $root, string $package): array
    {
        $file = $package . '.pc';

        return [
            $root . '/lib/pkgconfig/' . $file,
            $root . '/lib/*/pkgconfig/' . $file,
            $root . '/share/pkgconfig/' . $file,
            $root . '/opt/*/lib/pkgconfig/' . $file,
            $root . '/opt/*/share/pkgconfig/' . $file,
            $root . '/Cellar/*/*/lib/pkgconfig/' . $file,
            $root . '/Cellar/*/*/share/pkgconfig/' . $file,
        ];
    }

    /** @param list<string> $paths */
    private function appendDelimited(array &$paths, string $path): void
    {
        if ('' === $path) {
            return;
        }

        foreach (explode(\PATH_SEPARATOR, $path) as $part) {
            if ('' !== $part) {
                $paths[] = $part;
            }
        }
    }
}
