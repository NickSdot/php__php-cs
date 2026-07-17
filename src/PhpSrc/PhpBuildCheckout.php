<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrc;

use InternalsCS\Console\ConsoleIo;

use function array_map;
use function dirname;
use function escapeshellarg;
use function fclose;
use function implode;
use function is_dir;
use function is_resource;
use function mkdir;
use function proc_close;
use function proc_open;
use function stream_get_contents;

final readonly class PhpBuildCheckout
{
    public function prepare(PhpSrcRoot $sourceRoot, PhpBuildPaths $paths, ConsoleIo $io): PhpSrcRoot
    {
        $sourceDir = $paths->sourceDir();
        $this->cloneIfMissing($sourceRoot, $sourceDir, $io);
        $this->run($sourceDir, ['git', 'remote', 'set-url', 'origin', $sourceRoot->path]);

        $reference = $this->masterReference($sourceRoot, $sourceDir, $io);

        $this->run($sourceDir, ['git', 'checkout', '--detach', $reference]);
        $this->clean(PhpSrcRoot::fromPath($sourceDir));

        return PhpSrcRoot::fromPath($sourceDir);
    }

    public function clean(PhpSrcRoot $root): void
    {
        $this->run($root->path, ['git', 'reset', '--hard', 'HEAD']);
        $this->run($root->path, ['git', 'clean', '-fdx']);
    }

    private function cloneIfMissing(PhpSrcRoot $sourceRoot, string $sourceDir, ConsoleIo $io): void
    {
        if (is_dir($sourceDir . '/.git')) {
            return;
        }

        $parent = dirname($sourceDir);

        if (!is_dir($parent) && !mkdir($parent, 0o777, true)) {
            throw new \RuntimeException('Cannot create PHP runtime checkout directory: ' . $parent);
        }

        $io->out('Cloning PHP runtime source into ' . $sourceDir . "\n");
        $this->run($parent, ['git', 'clone', '--no-checkout', $sourceRoot->path, $sourceDir]);
    }

    private function masterReference(PhpSrcRoot $sourceRoot, string $sourceDir, ConsoleIo $io): string
    {
        if (!$this->optional($sourceDir, ['git', 'fetch', 'origin', 'master'])->ok()) {
            throw new \RuntimeException(
                'Cannot resolve local master from php-src checkout',
            );
        }

        $io->out("Using local master for PHP test runtime\n");

        return 'refs/remotes/origin/master';
    }
    /** @param list<string> $command */
    private function optional(string $cwd, array $command): PhpBuildProcessResult
    {
        return $this->process($cwd, $command);
    }

    /** @param list<string> $command */
    private function run(string $cwd, array $command): void
    {
        $result = $this->process($cwd, $command);

        if (!$result->ok()) {
            throw new \RuntimeException('Command failed: ' . $this->commandLabel($command) . "\n" . $result->stderr);
        }
    }

    /** @param list<string> $command */
    private function process(string $cwd, array $command): PhpBuildProcessResult
    {
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $cwd,
        );

        if (!is_resource($process)) {
            throw new \RuntimeException('Cannot run command: ' . $this->commandLabel($command));
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return new PhpBuildProcessResult(
            exitCode: proc_close($process),
            stdout: (string) $stdout,
            stderr: (string) $stderr,
        );
    }

    /** @param list<string> $command */
    private function commandLabel(array $command): string
    {
        return implode(' ', array_map(escapeshellarg(...), $command));
    }
}
