<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrc;

use InternalsCS\Console\ConsoleIo;

use function ctype_digit;
use function getenv;
use function is_executable;
use function is_file;
use function is_string;
use function mb_trim;
use function shell_exec;

final readonly class PhpBuild
{
    public function __construct(
        private PhpBuildProfile $profile = new PhpBuildProfile(),
        private PhpBuildState $state = new PhpBuildState(),
        private PhpBuildRunner $runner = new PhpBuildRunner(),
    ) {}

    public function ensure(PhpSrcRoot $root, PhpBuildPaths $paths, bool $force, ConsoleIo $io): void
    {
        if ($this->hasConfiguredPhpBinary()) {
            $io->out("Using configured PHP test binary\n");
            return;
        }

        $current = $this->state->current($root, $this->profile);
        $existing = PhpBuildMetadata::read($paths->metadata());

        if (!$force && $existing?->matches($current) && $paths->hasRunnableBinaries()) {
            $io->out('PHP test binaries are current in ' . $paths->outputDir . "\n");
            return;
        }

        $this->runner->build($root, $this->profile, $paths, $jobs = $this->defaultJobs(), $io);
        $current->write($paths->metadata());

        $io->out('Installed PHP CLI: ' . $paths->phpBinary() . "\n");
        $io->out('Installed PHP CGI: ' . $paths->cgiBinary() . "\n");
    }

    private function defaultJobs(): int
    {
        $cores = mb_trim((string) shell_exec('getconf _NPROCESSORS_ONLN 2>/dev/null'));

        if (ctype_digit($cores) && (int) $cores > 0) {
            return (int) $cores;
        }

        return 4;
    }

    private function hasConfiguredPhpBinary(): bool
    {
        $binary = getenv('INTERNALS_CS_TEST_PHP_EXECUTABLE');

        if (is_string($binary) && is_file($binary) && is_executable($binary)) {
            return true;
        }

        $binary = $_ENV['INTERNALS_CS_TEST_PHP_EXECUTABLE'] ?? null;

        return is_string($binary) && is_file($binary) && is_executable($binary);
    }
}
