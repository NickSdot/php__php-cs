<?php

declare(strict_types=1);

namespace InternalsCS\Command;

use InternalsCS\Console\Command;
use InternalsCS\Console\ConsoleIo;
use InternalsCS\Console\StderrConsoleIo;
use InternalsCS\FixerRunner;
use InternalsCS\PhpSrc\PhpBuild;
use InternalsCS\PhpSrc\PhpBuildPaths;
use InternalsCS\PhpSrc\PhpSrcRoot;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalFixer;
use InternalsCS\Support\Paths;

use function count;
use function dirname;
use function function_exists;
use function getcwd;
use function mb_strlen;
use function mb_substr;
use function str_starts_with;

final readonly class FixCommand implements Command
{
    public function __construct(
        private Paths $paths = new Paths(),
        private PhpBuild $phpBuild = new PhpBuild(),
    ) {}

    public function run(string $script, array $args, ConsoleIo $io): int
    {
        if (!function_exists('token_get_all')) {
            $io->err("fix requires the tokenizer extension\n");
            return 2;
        }

        try {
            $options = $this->options($args, $script, $io);
            $result = $this->runFixer($options, $io);
        } catch (CommandExit $exit) {
            return $exit->exitCode;
        } catch (\Throwable $e) {
            $io->err($e->getMessage() . "\n");
            return 2;
        }

        if ($options->print) {
            return $this->printResult($result, $io);
        }

        if ($options->check && $result['changed'] > 0) {
            return 1;
        }

        return $result['failed'] > 0 ? 1 : 0;
    }

    private function options(array $args, string $script, ConsoleIo $io): FixOptions
    {
        $workingDir = getcwd();
        $phpSrcDir = null;
        $check = false;
        $print = false;
        $forcePhpBinaryRebuild = false;
        $targets = [];

        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];

            if ('--help' === $arg || '-h' === $arg) {
                $this->usage($script, $io);
                throw new CommandExit(0);
            }

            if ('--check' === $arg) {
                $check = true;
                continue;
            }

            if ('--print' === $arg) {
                $print = true;
                continue;
            }

            if ('--force-php-binary-rebuild' === $arg) {
                $forcePhpBinaryRebuild = true;
                continue;
            }

            if ('--php-src-dir' === $arg) {
                $phpSrcDir = $this->value($args, ++$i, '--php-src-dir');
                continue;
            }

            if (str_starts_with((string) $arg, '--php-src-dir=')) {
                $phpSrcDir = mb_substr((string) $arg, mb_strlen('--php-src-dir='));
                continue;
            }

            if (str_starts_with((string) $arg, '-')) {
                throw new CommandFailure('Unknown option: ' . $arg);
            }

            $targets[] = $this->paths->existingRelativeTo($arg, $workingDir);
        }

        if (null === $phpSrcDir) {
            throw new CommandFailure('--php-src-dir /path/to/php-src is required');
        }

        if ($check && $print) {
            throw new CommandFailure('--check and --print cannot be used together');
        }

        return new FixOptions(
            phpSrcRoot: PhpSrcRoot::fromPath($phpSrcDir),
            targets: $targets,
            check: $check,
            print: $print,
            forcePhpBinaryRebuild: $forcePhpBinaryRebuild,
        );
    }

    /** @return array{changed: int, failed: int}|array{changed: bool, failed: bool, output: string, failure: string|null} */
    private function runFixer(FixOptions $options, ConsoleIo $io): array
    {
        if (!$options->check) {
            $this->phpBuild->ensure(
                root: $options->phpSrcRoot,
                paths: PhpBuildPaths::default(dirname(__DIR__, 2)),
                force: $options->forcePhpBinaryRebuild,
                io: $options->print ? new StderrConsoleIo($io) : $io,
            );
        }

        $runner = new FixerRunner($options->phpSrcRoot->path, [
            CanonicalFixer::class,
        ]);
        $files = $runner->collectFiles($options->targets);

        if ($options->print) {
            return $runner->print($files);
        }

        return $runner->run($files, $options->check);
    }

    /** @param array{changed: bool, failed: bool, output: string, failure: string|null} $result */
    private function printResult(array $result, ConsoleIo $io): int
    {
        if ($result['failed']) {
            $io->err(($result['failure'] ?? 'unknown reason') . "\n");
            return 1;
        }

        $io->out($result['output']);

        return 0;
    }

    private function value(array $args, int $index, string $option): string
    {
        return $args[$index] ?? throw new CommandFailure($option . ' requires a value');
    }

    private function usage(string $script, ConsoleIo $io): void
    {
        $io->out("Usage: php bin/$script --php-src-dir dir [--check|--print] [--force-php-binary-rebuild] [path ...]\n");
    }
}
