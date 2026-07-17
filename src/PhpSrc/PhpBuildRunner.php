<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrc;

use InternalsCS\Console\ConsoleIo;

use function array_map;
use function chmod;
use function copy;
use function dirname;
use function fclose;
use function implode;
use function is_dir;
use function is_file;
use function is_resource;
use function is_string;
use function max;
use function mkdir;
use function proc_close;
use function proc_open;
use function stream_get_contents;

final readonly class PhpBuildRunner
{
    public function __construct(
        private PhpBuildEnvironment $environment = new PhpBuildEnvironment(),
    ) {}

    public function build(PhpSrcRoot $root, PhpBuildProfile $profile, PhpBuildPaths $paths, int $jobs, ConsoleIo $io): void
    {
        $environment = $this->environment->variables($profile->pkgConfigPackages());

        $this->cleanPreviousBuild($root, $environment, $io);

        $this->run($root, ['./buildconf', '--force'], $environment, $io);
        $this->run($root, ['./configure', ...$profile->configureArgs()], $environment, $io);
        $this->run($root, ['make', '-j' . max(1, $jobs), ...$profile->makeTargets()], $environment, $io);
        $this->installBinary($root->path . '/sapi/cli/php', $paths->phpBinary());
        $this->installBinary($root->path . '/sapi/cgi/php-cgi', $paths->cgiBinary());
    }

    /** @param array<string, string> $environment */
    private function cleanPreviousBuild(PhpSrcRoot $root, array $environment, ConsoleIo $io): void
    {
        if (!is_file($root->path . '/Makefile')) {
            return;
        }

        $this->run($root, ['make', 'distclean'], $environment, $io);
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     */
    private function run(PhpSrcRoot $root, array $command, array $environment, ConsoleIo $io): void
    {
        $io->out('$ ' . implode(' ', array_map(escapeshellarg(...), $command)) . "\n");

        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $root->path,
            $environment,
        );

        if (!is_resource($process)) {
            throw new \RuntimeException('Cannot run build command');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if (is_string($stdout) && '' !== $stdout) {
            $io->out($stdout);
        }

        if (is_string($stderr) && '' !== $stderr) {
            $io->err($stderr);
        }

        if (0 !== $exitCode) {
            throw new \RuntimeException('Build command failed with exit code ' . $exitCode);
        }
    }

    private function installBinary(string $source, string $target): void
    {
        if (!is_file($source)) {
            throw new \RuntimeException('Expected PHP build artifact is missing: ' . $source);
        }

        $dir = dirname($target);

        if (!is_dir($dir) && !mkdir($dir, 0o777, true)) {
            throw new \RuntimeException('Cannot create PHP binary output directory: ' . $dir);
        }

        if (!copy($source, $target)) {
            throw new \RuntimeException('Cannot copy PHP build artifact to: ' . $target);
        }

        chmod($target, 0o755);
    }
}
