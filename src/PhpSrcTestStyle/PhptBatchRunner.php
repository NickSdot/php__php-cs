<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle;

use InternalsCS\Support\ProcessEnvironment;

use function array_fill;
use function array_shift;
use function count;
use function explode;
use function fclose;
use function file;
use function file_put_contents;
use function implode;
use function is_resource;
use function proc_close;
use function proc_open;
use function str_contains;
use function stream_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class PhptBatchRunner
{
    /**
     * @param list<PhptFile> $files
     *
     * @return list<array{status: string, output: string, exitCode: int}>
     */
    public function run(array $files): array
    {
        if ([] === $files) {
            return [];
        }

        $results = array_fill(0, count($files), null);
        $groups = [];

        foreach ($files as $index => $file) {
            $runtime = $file->testRuntime();
            $key = $file->rootDir . "\0" . $runtime->phpBinary . "\0" . ($runtime->phpCgiBinary ?? '');
            $groups[$key][] = [$index, $file];
        }

        foreach ($groups as $group) {
            $groupResults = $this->runGroup($group);

            foreach ($groupResults as $index => $result) {
                $results[$index] = $result;
            }
        }

        /** @var list<array{status: string, output: string, exitCode: int}> $results */
        return $results;
    }

    /**
     * @param list<array{0: int, 1: PhptFile}> $group
     *
     * @return array<int, array{status: string, output: string, exitCode: int}>
     */
    private function runGroup(array $group): array
    {
        $first = $group[0][1];
        $runtime = $first->testRuntime();
        $listPath = tempnam(sys_get_temp_dir(), 'php-src-cs-tests-');
        $resultPath = tempnam(sys_get_temp_dir(), 'php-src-cs-results-');

        if (false === $listPath || false === $resultPath) {
            throw new \RuntimeException('Cannot create PHPT batch files');
        }

        try {
            $paths = [];

            foreach ($group as [, $file]) {
                $file->cleanupArtifacts();
                $paths[] = $file->path;
            }

            if (false === file_put_contents($listPath, implode("\n", $paths) . "\n")) {
                throw new \RuntimeException('Cannot write PHPT batch test list');
            }

            $cmd = [
                $runtime->phpBinary,
                'run-tests.php',
                '-q',
                '--no-progress',
                '--no-color',
                '-r',
                $listPath,
                // Keep the pass/fail decision tied to each PHPT inside the shared harness.
                '-W',
                $resultPath,
            ];
            $descriptorSpec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $env = new ProcessEnvironment()->variables();
            $env['NO_INTERACTION'] = '1';
            $env['REPORT_EXIT_STATUS'] = '1';
            $env['TEST_PHP_EXECUTABLE'] = $runtime->phpBinary;

            if (null !== $runtime->phpCgiBinary) {
                $env['TEST_PHP_CGI_EXECUTABLE'] = $runtime->phpCgiBinary;
            }

            $process = proc_open($cmd, $descriptorSpec, $pipes, $first->rootDir, $env);
            if (!is_resource($process)) {
                throw new \RuntimeException('Cannot run PHPT batch');
            }

            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            $output = $stdout . $stderr;

            return $this->results($group, $resultPath, $output, $exitCode);
        } finally {
            @unlink($listPath);
            @unlink($resultPath);
        }
    }

    /**
     * @param list<array{0: int, 1: PhptFile}> $group
     *
     * @return array<int, array{status: string, output: string, exitCode: int}>
     */
    private function results(array $group, string $resultPath, string $output, int $exitCode): array
    {
        $reported = [];
        $lines = file($resultPath, FILE_IGNORE_NEW_LINES);

        if (false !== $lines) {
            foreach ($lines as $line) {
                $parts = explode("\t", $line, 2);
                if (2 !== count($parts)) {
                    continue;
                }

                [$status, $path] = $parts;
                $reported[$path][] = $status;
            }
        }

        $results = [];

        foreach ($group as [$index, $file]) {
            $path = $file->path;
            $statuses = $reported[$path] ?? [];
            $reportedStatus = array_shift($statuses);
            $reported[$path] = $statuses;

            if (null === $reportedStatus) {
                $results[$index] = [
                    'status' => 'unknown',
                    'output' => "PHPT batch did not report a result for $path\n$output",
                    'exitCode' => $exitCode,
                ];
                continue;
            }

            $status = $this->status($reportedStatus);
            $results[$index] = [
                'status' => $status,
                'output' => $this->resultOutput($output, $path, $status),
                'exitCode' => 'PASS' === $status ? 0 : 1,
            ];
        }

        return $results;
    }

    private function status(string $reportedStatus): string
    {
        return match ($reportedStatus) {
            'PASSED' => 'PASS',
            'SKIPPED' => 'SKIP',
            'FAILED' => 'FAIL',
            'BORKED' => 'BORK',
            'WARNED' => 'WARN',
            'XFAILED' => 'XFAIL',
            'XLEAKED' => 'XLEAK',
            'LEAKED' => 'LEAK',
            default => $reportedStatus,
        };
    }

    private function resultOutput(string $output, string $path, string $status): string
    {
        foreach (explode("\n", $output) as $line) {
            if (str_contains($line, "[$path]")) {
                return $line;
            }
        }

        return "$status $path";
    }
}
