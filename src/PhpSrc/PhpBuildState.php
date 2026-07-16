<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrc;

use function fclose;
use function is_resource;
use function mb_trim;
use function proc_close;
use function proc_open;
use function sha1;
use function stream_get_contents;

final readonly class PhpBuildState
{
    public function current(PhpSrcRoot $root, PhpBuildProfile $profile): PhpBuildMetadata
    {
        return new PhpBuildMetadata(
            phpSrcDir: $root->path,
            head: $this->git($root, 'rev-parse', 'HEAD'),
            statusHash: sha1($this->git($root, 'status', '--short')),
            profileSignature: $profile->signature(),
        );
    }

    private function git(PhpSrcRoot $root, string ...$args): string
    {
        $process = proc_open(
            ['git', '-C', $root->path, ...$args],
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

        return mb_trim((string) $stdout);
    }
}
