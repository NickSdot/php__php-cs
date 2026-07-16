<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrc;

use InternalsCS\Support\ProcessEnvironment;

use function implode;

final readonly class PhpBuildEnvironment
{
    public function __construct(
        private ProcessEnvironment $processEnvironment = new ProcessEnvironment(),
        private PkgConfigPathResolver $pkgConfigPaths = new PkgConfigPathResolver(),
    ) {}

    /**
     * @param list<string> $pkgConfigPackages
     *
     * @return array<string, string>
     */
    public function variables(array $pkgConfigPackages): array
    {
        $environment = $this->processEnvironment->variables();
        $paths = $this->pkgConfigPaths->resolve($environment, $pkgConfigPackages);

        if ([] === $paths) {
            return $environment;
        }

        $environment['PKG_CONFIG_PATH'] = implode(\PATH_SEPARATOR, $paths);

        return $environment;
    }
}
