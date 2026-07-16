<?php

declare(strict_types=1);

namespace InternalsCS\Support;

use function count;
use function fclose;
use function file_get_contents;
use function implode;
use function is_resource;
use function preg_split;
use function proc_close;
use function proc_open;
use function stream_get_contents;

final readonly class UnifiedDiff
{
    public function betweenFiles(string $oldPath, string $newPath, string $oldLabel, string $newLabel): string
    {
        $diff = $this->fromCommand($oldPath, $newPath, $oldLabel, $newLabel);

        return $diff ?? $this->fallback($oldPath, $newPath, $oldLabel, $newLabel);
    }

    private function fromCommand(string $oldPath, string $newPath, string $oldLabel, string $newLabel): ?string
    {
        $process = proc_open(
            ['diff', '-u', '--label', $oldLabel, '--label', $newLabel, $oldPath, $newPath],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        if (!is_resource($process)) {
            return null;
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if (0 === $exitCode || 1 === $exitCode) {
            return (string) $stdout;
        }

        return null;
    }

    private function fallback(string $oldPath, string $newPath, string $oldLabel, string $newLabel): string
    {
        $old = file_get_contents($oldPath);
        $new = file_get_contents($newPath);

        if (false === $old || false === $new) {
            throw new \RuntimeException('Cannot read files for diff');
        }

        if ($old === $new) {
            return '';
        }

        $oldLines = $this->splitLines($old);
        $newLines = $this->splitLines($new);
        $lines = [
            "--- $oldLabel\n",
            "+++ $newLabel\n",
            '@@ -1,' . count($oldLines) . ' +1,' . count($newLines) . " @@\n",
        ];

        foreach ($oldLines as $line) {
            $lines[] = '-' . $line;
        }

        foreach ($newLines as $line) {
            $lines[] = '+' . $line;
        }

        return implode('', $lines);
    }

    /** @return list<string> */
    private function splitLines(string $contents): array
    {
        if ('' === $contents) {
            return [];
        }

        $lines = preg_split('/(?<=\n)/', $contents, -1, PREG_SPLIT_NO_EMPTY);

        return false === $lines ? [] : $lines;
    }
}
