<?php

declare(strict_types=1);

namespace InternalsCS\Command;

use InternalsCS\Console\Command;
use InternalsCS\Console\ConsoleIo;
use InternalsCS\Console\StderrConsoleIo;
use InternalsCS\FixRunEntry;
use InternalsCS\FixRunReportWriter;
use InternalsCS\FixRunResult;
use InternalsCS\FixerRegistry;
use InternalsCS\FixerRunner;
use InternalsCS\PhpSrc\PhpSrcRoot;
use InternalsCS\PhpSrcTestStyle\PhptTestRuntimeResolver;
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
        private FixerRegistry $fixers = new FixerRegistry(),
        private FixRunReportWriter $reports = new FixRunReportWriter(),
        private PhptTestRuntimeResolver $runtime = new PhptTestRuntimeResolver(),
    ) {}

    public function run(string $script, array $args, ConsoleIo $io): int
    {
        if (!function_exists('token_get_all')) {
            $io->err("fix requires the tokenizer extension\n");
            return 2;
        }

        try {
            $options = $this->options($args, $script, $io);
        } catch (CommandExit $exit) {
            return $exit->exitCode;
        } catch (\Throwable $e) {
            $io->err($e->getMessage() . "\n");
            return 2;
        }

        try {
            if ($options->print) {
                $result = $this->runPrintFixer($options, $io);

                return $this->printResult($result, $io);
            }

            $result = $this->runFixer($options, $io);
            $reportPath = $this->writeRunReport($options, $result);
        } catch (CommandFailure $e) {
            $io->err($e->getMessage() . "\n");
            return 2;
        }

        $io->out('Report: ' . $this->paths->relative($reportPath, $this->toolRoot()) . "\n");

        if ($options->check && $result->changed() > 0) {
            return 1;
        }

        return 0;
    }

    /** @param list<string> $args */
    private function options(array $args, string $script, ConsoleIo $io): FixOptions
    {
        $workingDir = getcwd();

        if (false === $workingDir) {
            throw new CommandFailure('Cannot determine current working directory');
        }

        $phpSrcDir = null;
        $check = false;
        $print = false;
        $fixers = [];
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

            if ('--fixer' === $arg) {
                $fixers[] = $this->value($args, ++$i, '--fixer');
                continue;
            }

            if (str_starts_with($arg, '--fixer=')) {
                $fixers[] = mb_substr($arg, mb_strlen('--fixer='));
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

            if (str_starts_with($arg, '-')) {
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
            fixerClasses: $this->fixerClasses($fixers),
            check: $check,
            print: $print,
        );
    }

    private function runFixer(FixOptions $options, ConsoleIo $io): FixRunResult
    {
        if (!$options->check) {
            $this->announceRuntime($options->phpSrcRoot, $io);
        }

        $runner = new FixerRunner($options->phpSrcRoot->path, $options->fixerClasses);
        $files = $runner->collectFiles($options->targets);

        return $runner->run(
            files: $files,
            check: $options->check,
            onEntry: static function (FixRunEntry $entry) use ($io): void {
                $io->out($entry->consoleLine() . "\n");
            },
        );
    }

    private function writeRunReport(FixOptions $options, FixRunResult $result): string
    {
        return $this->reports->write(
            reportDir: $this->toolRoot() . '/var/fix-runs',
            timestamp: new \DateTimeImmutable(),
            phpSrcDir: $options->phpSrcRoot->path,
            targets: $options->targets,
            fixers: $this->fixerNames($options->fixerClasses),
            result: $result,
        );
    }

    /** @return array{changed: bool, failed: bool, output: string, failure: string|null} */
    private function runPrintFixer(FixOptions $options, ConsoleIo $io): array
    {
        $this->announceRuntime($options->phpSrcRoot, new StderrConsoleIo($io));

        $runner = new FixerRunner($options->phpSrcRoot->path, $options->fixerClasses);

        return $runner->print($runner->collectFiles($options->targets));
    }

    private function announceRuntime(PhpSrcRoot $root, ConsoleIo $io): void
    {
        try {
            $runtime = $this->runtime->resolve($root->path);
        } catch (\RuntimeException $e) {
            throw new CommandFailure($e->getMessage());
        }

        $io->out('Using PHP test binary: ' . $runtime->phpBinary . "\n");
    }

    /**
     * @param list<class-string<\InternalsCS\Fixer>> $fixerClasses
     * @return list<string>
     */
    private function fixerNames(array $fixerClasses): array
    {
        $names = [];

        foreach ($fixerClasses as $fixerClass) {
            $fixer = new $fixerClass();
            $names[] = $fixer->name();
        }

        return $names;
    }

    /**
     * @param list<string> $names
     * @return list<class-string<\InternalsCS\Fixer>>
     */
    private function fixerClasses(array $names): array
    {
        try {
            return $this->fixers->selected($names);
        } catch (\InvalidArgumentException $e) {
            throw new CommandFailure($e->getMessage());
        }
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

    /** @param list<string> $args */
    private function value(array $args, int $index, string $option): string
    {
        return $args[$index] ?? throw new CommandFailure($option . ' requires a value');
    }

    private function usage(string $script, ConsoleIo $io): void
    {
        $io->out("Usage: php bin/$script --php-src-dir dir [--check|--print] [--fixer name] [path ...]\n");
        $io->out("Known fixers: " . $this->fixers->knownFixersLine() . "\n");
    }

    private function toolRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
