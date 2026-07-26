<?php

declare(strict_types=1);

namespace InternalsCS\Command;

use InternalsCS\Console\Command;
use InternalsCS\Console\ConsoleIo;
use InternalsCS\Fixers\ExceptionOutput\ExceptionOutputFixtureDiscovery;
use InternalsCS\Fixers\FinalNewline\FinalNewlineFixtureDiscovery;
use InternalsCS\Fixture\FixtureDiscovery;
use InternalsCS\Fixture\FixtureGenerationOptions;
use InternalsCS\Fixture\FixtureGenerator;
use InternalsCS\PhpSrc\PhpBuild;
use InternalsCS\PhpSrc\PhpBuildPaths;
use InternalsCS\PhpSrc\PhpSrcRoot;
use InternalsCS\Support\Paths;

use function array_all;
use function array_any;
use function count;
use function dirname;
use function getcwd;
use function mb_strlen;
use function mb_substr;
use function str_starts_with;

final readonly class GenerateCommand implements Command
{
    /** @param list<FixtureDiscovery> $discoveries */
    public function __construct(
        private array $discoveries = [
            new ExceptionOutputFixtureDiscovery(),
            new FinalNewlineFixtureDiscovery(),
        ],
        private Paths $paths = new Paths(),
        private FixtureGenerator $generator = new FixtureGenerator(),
        private GenerateRunPrinter $printer = new GenerateRunPrinter(),
        private PhpBuild $phpBuild = new PhpBuild(),
    ) {}

    public function run(string $script, array $args, ConsoleIo $io): int
    {
        if ($this->wantsCommandHelp($args)) {
            $this->usage($script, $io);
            return 0;
        }

        try {
            $options = $this->options($args);
        } catch (CommandExit $exit) {
            $this->usage($script, $io);
            return $exit->exitCode;
        } catch (\Throwable $e) {
            $io->err($e->getMessage() . "\n");
            return 2;
        }

        if (!$this->checkRuntime($io)) {
            return 2;
        }

        $phpTestRuntimeRoot = $options->phpSrcRoot;

        if ($options->write && $this->requiresPhpTestRuntime()) {
            try {
                $phpTestRuntimeRoot = $this->phpBuild->ensure(
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

        $options = $options->withPhpTestRuntimeRoot($phpTestRuntimeRoot);

        $result = $this->generator->generate(new FixtureGenerationOptions(
            phpSrcRoot: $options->phpSrcRoot,
            phpTestRuntimeRoot: $options->phpTestRuntimeRoot,
            fixturesRoot: $options->fixturesRoot,
            reportsRoot: $options->reportsRoot,
            paths: $options->paths,
            allowDirty: $options->allowDirty,
            write: $options->write,
            refreshOnly: $options->refreshOnly,
        ), $this->discoveries);

        if ($result->sourceFiles > 0) {
            $io->out('Scanned ' . $result->sourceFiles . " source files once\n\n");
        }

        foreach ($result->runs as $run) {
            $this->printer->print($run, $io);
        }

        if (null !== $result->reportPath) {
            $io->out('Report: ' . $this->paths->relative($result->reportPath, $this->toolRoot()) . "\n");
        }

        return $result->failed() ? 1 : 0;
    }

    /** @param list<string> $args */
    private function options(array $args): GenerateOptions
    {
        $workingDir = getcwd();

        if (false === $workingDir) {
            throw new CommandFailure('Cannot determine current working directory');
        }

        $phpSrcDir = null;
        $targetDir = $this->toolRoot();
        $allowDirty = false;
        $write = false;
        $forcePhpBinaryRebuild = false;
        $refreshOnly = false;
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

            if ('--refresh-only' === $arg) {
                $refreshOnly = true;
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

            // Redirect generated artefacts under a project-shaped target root:
            // <target>/tests/Fixtures and <target>/reports.
            if ('--target-dir' === $arg) {
                $targetDir = $this->value($args, ++$i, '--target-dir');
                continue;
            }

            // Same as --target-dir DIR, but in --target-dir=DIR form.
            if (str_starts_with($arg, '--target-dir=')) {
                $targetDir = mb_substr($arg, mb_strlen('--target-dir='));
                continue;
            }

            if (str_starts_with($arg, '-')) {
                throw new CommandFailure('Unknown option: ' . $arg);
            }

            if ($this->isFixerName($arg)) {
                throw new CommandFailure('generate scans all fixers; pass php-src paths, not fixer names: ' . $arg);
            }

            $paths[] = $arg;
        }

        if (null === $phpSrcDir) {
            throw new CommandFailure('--php-src-dir /path/to/php-src is required');
        }

        $targetDir = $this->paths->absolute($targetDir, $workingDir);

        return new GenerateOptions(
            phpSrcRoot: PhpSrcRoot::fromPath($phpSrcDir),
            phpTestRuntimeRoot: PhpSrcRoot::fromPath($phpSrcDir),
            fixturesRoot: $targetDir . '/tests/Fixtures',
            reportsRoot: $targetDir . '/reports',
            paths: $paths,
            allowDirty: $allowDirty,
            write: $write,
            forcePhpBinaryRebuild: $forcePhpBinaryRebuild,
            refreshOnly: $refreshOnly,
        );
    }

    /** @param list<string> $args */
    private function wantsCommandHelp(array $args): bool
    {
        return ($args[0] ?? null) === '--help' || ($args[0] ?? null) === '-h';
    }

    /** @param list<string> $args */
    private function value(array $args, int $index, string $option): string
    {
        return $args[$index] ?? throw new CommandFailure($option . ' requires a value');
    }

    private function usage(string $script, ConsoleIo $io): void
    {
        $io->out(<<<USAGE
            Usage:
              php bin/$script --php-src-dir DIR [options] [path ...]

            Scans php-src once and generates fixture coverage for every fixer.

            Options:
              --php-src-dir DIR              Required php-src checkout.
              --write                        Write fixtures and reports  Without it is a dry run.
              --refresh-only                 Refresh existing fixtures; import no new old.phpt files.
              --allow-dirty                  Allow discovery from a dirty php-src checkout.
              --force-php-binary-rebuild     Rebuild the managed PHP test runtime.
              -h, --help                     Show this help.

            USAGE);
    }

    private function checkRuntime(ConsoleIo $io): bool
    {
        return array_all($this->discoveries, fn(FixtureDiscovery $discovery): bool => $discovery->checkRuntime($io));
    }

    private function requiresPhpTestRuntime(): bool
    {
        return array_any($this->discoveries, fn(FixtureDiscovery $discovery): bool => $discovery->requiresPhpTestRuntime());
    }

    private function isFixerName(string $arg): bool
    {
        return array_any($this->discoveries, fn(FixtureDiscovery $discovery): bool => $discovery->fixerName() === $arg);
    }

    private function toolRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
