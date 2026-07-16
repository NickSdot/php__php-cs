<?php

declare(strict_types=1);

namespace InternalsCS\Support;

use function fclose;
use function is_resource;
use function mb_trim;
use function proc_close;
use function proc_open;
use function stream_get_contents;

final readonly class GitStatus
{
    public function isDirty(string $rootDir): bool
    {
        $output = $this->status($rootDir);

        return '' !== mb_trim($output);
    }

    public function status(string $rootDir): string
    {
        $process = proc_open(
            ['git', '-C', $rootDir, 'status', '--short'],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        if (!is_resource($process)) {
            return '';
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return (string) $stdout;
    }
}
