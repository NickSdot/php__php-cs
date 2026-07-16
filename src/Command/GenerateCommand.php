<?php

declare(strict_types=1);

namespace InternalsCS\Command;

use InternalsCS\Console\Command;
use InternalsCS\Console\ConsoleIo;
use InternalsCS\Fixture\FixtureGenerationOptions;
use InternalsCS\PhpSrc\PhpBuild;
use InternalsCS\PhpSrc\PhpBuildPaths;
use InternalsCS\PhpSrc\PhpSrcRoot;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Command\ExceptionOutputGenerateTarget;
use InternalsCS\Support\GitStatus;
use InternalsCS\Support\Paths;

use function array_find;
use function array_slice;
use function count;
use function dirname;
use function getcwd;
use function mb_strlen;
use function mb_substr;
use function str_starts_with;

final readonly class GenerateCommand implements Command
{
    /** @param list<GenerateTarget> $targets */
    public function __construct(
        private array $targets = [
            new ExceptionOutputGenerateTarget(),
        ],
        private Paths $paths = new Paths(),
        private PhpBuild $phpBuild = new PhpBuild(),
        private GitStatus $git = new GitStatus(),
    ) {}

    public function run(string $script, array $args, ConsoleIo $io): int
    {
        if ($this->wantsCommandHelp($args)) {
            $this->usage($script, $io);
            return 0;
        }

        $targetName = $this->targetName($args);
        $target = null === $targetName
            ? $this->defaultTarget()
            : $this->target($targetName);

        if (null === $target) {
            $io->err('Unknown generate target: ' . $targetName . "\n");
            $this->usage($script, $io);
            return 2;
        }

        if (!$target->checkRuntime($io)) {
            return 2;
        }

        try {
            $options = $this->options($this->targetArgs($args), $target);
        } catch (CommandExit $exit) {
            $this->targetUsage($script, $target, $targetName, $io);
            return $exit->exitCode;
        } catch (\Throwable $e) {
            $io->err($e->getMessage() . "\n");
            return 2;
        }

        if (!$options->allowDirty && $this->git->isDirty($options->phpSrcRoot->path)) {
            $io->err("source checkout is dirty; pass --allow-dirty to generate anyway\n");
            return 1;
        }

        if ($options->write && $target->requiresPhpTestRuntime()) {
            try {
                $this->phpBuild->ensure(
                    root: $options->phpSrcRoot,
                    paths: PhpBuildPaths::default($this->toolRoot()),
                    force: $options->forcePhpBinaryRebuild,
                    io: $io,
                );
            } catch (\Throwable $e) {
                $io->err($e->getMessage() . "\n");
                return 1;
            }
        }

        $result = $target->generator()->generate(new FixtureGenerationOptions(
            sourceRoot: $options->phpSrcRoot->path,
            fixturesDir: $options->fixturesDir,
            reportsDir: $options->reportsDir,
            paths: $options->paths,
            excludedRoots: [
                $options->fixturesDir,
            ],
            extensions: $target->sourceExtensions(),
            runner: $target->rewriteRunner($options),
            allowDirty: $options->allowDirty,
            write: $options->write,
        ));

        return $target->printResult($result, $io);
    }

    /** @param list<string> $args */
    private function options(array $args, GenerateTarget $target): GenerateOptions
    {
        $workingDir = getcwd();

        if (false === $workingDir) {
            throw new CommandFailure('Cannot determine current working directory');
        }

        $phpSrcDir = null;
        $fixturesDir = $target->defaultFixturesDir($this->toolRoot());
        $reportsDir = $target->defaultReportsDir($this->toolRoot());
        $allowDirty = false;
        $write = false;
        $forcePhpBinaryRebuild = false;
        $paths = [];

        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];

            if ('--help' === $arg || '-h' === $arg) {
                throw new CommandExit(0);
            }

            if ('--allow-dirty' === $arg) {
                $allowDirty = true;
                continue;
            }

            if ('--write' === $arg) {
                $write = true;
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

            if (str_starts_with($arg, '--php-src-dir=')) {
                $phpSrcDir = mb_substr($arg, mb_strlen('--php-src-dir='));
                continue;
            }

            if ('--fixtures-dir' === $arg) {
                $fixturesDir = $this->value($args, ++$i, '--fixtures-dir');
                continue;
            }

            if (str_starts_with($arg, '--fixtures-dir=')) {
                $fixturesDir = mb_substr($arg, mb_strlen('--fixtures-dir='));
                continue;
            }

            if ('--output-dir' === $arg || '--reports-dir' === $arg) {
                $reportsDir = $this->value($args, ++$i, $arg);
                continue;
            }

            if (str_starts_with($arg, '--output-dir=')) {
                $reportsDir = mb_substr($arg, mb_strlen('--output-dir='));
                continue;
            }

            if (str_starts_with($arg, '--reports-dir=')) {
                $reportsDir = mb_substr($arg, mb_strlen('--reports-dir='));
                continue;
            }

            if (str_starts_with($arg, '-')) {
                throw new CommandFailure('Unknown option: ' . $arg);
            }

            $paths[] = $arg;
        }

        if (null === $phpSrcDir) {
            throw new CommandFailure('--php-src-dir /path/to/php-src is required');
        }

        return new GenerateOptions(
            phpSrcRoot: PhpSrcRoot::fromPath($phpSrcDir),
            fixturesDir: $this->paths->absolute($fixturesDir, $workingDir),
            reportsDir: $this->paths->absolute($reportsDir, $workingDir),
            paths: $paths,
            allowDirty: $allowDirty,
            write: $write,
            forcePhpBinaryRebuild: $forcePhpBinaryRebuild,
        );
    }

    /** @param list<string> $args */
    private function wantsCommandHelp(array $args): bool
    {
        return ($args[0] ?? null) === '--help' || ($args[0] ?? null) === '-h';
    }

    /** @param list<string> $args */
    private function targetName(array $args): ?string
    {
        $first = $args[0] ?? null;

        if (null === $first || str_starts_with($first, '-')) {
            return null;
        }

        return $first;
    }

    /**
     * @param list<string> $args
     * @return list<string>
     */
    private function targetArgs(array $args): array
    {
        return null === $this->targetName($args) ? $args : array_slice($args, 1);
    }

    private function defaultTarget(): GenerateTarget
    {
        return $this->targets[0];
    }

    private function target(string $name): ?GenerateTarget
    {
        return array_find($this->targets, fn($target) => $target->name() === $name);
    }

    /** @param list<string> $args */
    private function value(array $args, int $index, string $option): string
    {
        return $args[$index] ?? throw new CommandFailure($option . ' requires a value');
    }

    private function usage(string $script, ConsoleIo $io): void
    {
        $io->out("Usage: php bin/$script [target] [options]\n");
        $io->out("\n");
        $io->out("Targets:\n");

        foreach ($this->targets as $target) {
            $io->out('  ' . $target->name() . '  ' . $target->description() . "\n");
        }

        $io->out("\n");
        $io->out("Omitting target uses " . $this->defaultTarget()->name() . ".\n");
        $io->out("Run php bin/$script <target> --help for generator options.\n");
    }

    private function targetUsage(string $script, GenerateTarget $target, ?string $targetName, ConsoleIo $io): void
    {
        $targetScript = null === $targetName ? $script : $script . ' ' . $target->name();

        $io->out("Usage: php bin/$targetScript --php-src-dir dir [--write] [--fixtures-dir dir] [--reports-dir dir] [--allow-dirty] [--force-php-binary-rebuild] [path ...]\n");
        $io->out($target->description() . ". Writes only with --write.\n");
    }

    private function toolRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
