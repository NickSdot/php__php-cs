<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle;

use function dirname;
use function getenv;
use function is_executable;
use function is_file;
use function is_string;
use function realpath;

final readonly class PhptTestRuntimeResolver
{
    private string $toolRoot;

    public function __construct(?string $toolRoot = null)
    {
        $this->toolRoot = $toolRoot ?? dirname(__DIR__, 2);
    }

    public function resolve(string $phpSrcRoot): PhptTestRuntime
    {
        $phpBinary = $this->configuredExecutable('INTERNALS_CS_TEST_PHP_EXECUTABLE')
            ?? $this->phpSrcExecutable($phpSrcRoot, 'sapi/cli/php')
            ?? $this->managedRuntimeExecutable($phpSrcRoot, 'php');

        if (null === $phpBinary) {
            throw new \RuntimeException(
                'No PHP test binary found. Build php-src first so sapi/cli/php exists, '
                    . 'or set INTERNALS_CS_TEST_PHP_EXECUTABLE.',
            );
        }

        return new PhptTestRuntime(
            phpBinary: $phpBinary,
            phpCgiBinary: $this->configuredExecutable('INTERNALS_CS_TEST_PHP_CGI_EXECUTABLE')
                ?? $this->phpSrcExecutable($phpSrcRoot, 'sapi/cgi/php-cgi')
                ?? $this->managedRuntimeExecutable($phpSrcRoot, 'php-cgi'),
        );
    }

    private function configuredExecutable(string $name): ?string
    {
        $configured = getenv($name);

        if (is_string($configured) && is_file($configured) && is_executable($configured)) {
            return $configured;
        }

        $configured = $_ENV[$name] ?? null;

        if (is_string($configured) && is_file($configured) && is_executable($configured)) {
            return $configured;
        }

        return null;
    }

    private function phpSrcExecutable(string $phpSrcRoot, string $relativePath): ?string
    {
        $path = $phpSrcRoot . DIRECTORY_SEPARATOR . $relativePath;

        return is_file($path) && is_executable($path) ? $path : null;
    }

    private function managedRuntimeExecutable(string $phpSrcRoot, string $name): ?string
    {
        if (!$this->isManagedRuntimeSource($phpSrcRoot)) {
            return null;
        }

        $path = $this->toolRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'php-test-runtime' . DIRECTORY_SEPARATOR . $name;

        return is_file($path) && is_executable($path) ? $path : null;
    }

    private function isManagedRuntimeSource(string $phpSrcRoot): bool
    {
        $source = realpath($phpSrcRoot);
        $runtime = realpath($this->toolRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'php-test-runtime' . DIRECTORY_SEPARATOR . 'source');

        return false !== $source && false !== $runtime && $source === $runtime;
    }
}
