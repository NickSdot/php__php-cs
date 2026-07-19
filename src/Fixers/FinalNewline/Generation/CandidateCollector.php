<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\FinalNewline\Generation;

use InternalsCS\Fixers\FinalNewline\FinalNewline;
use InternalsCS\SourceFile;

use function preg_match;

final readonly class CandidateCollector
{
    public function __construct(
        private FinalNewline $finalNewline = new FinalNewline(),
    ) {}

    /** @return list<Candidate> */
    public function collect(SourceFile $source): array
    {
        if ($this->finalNewline->isNormalized($source->contents)) {
            return [];
        }

        return [
            new Candidate(
                sourcePath: $source->path,
                relativePath: $source->relativePath(),
                fixtureKey: $this->key($source->contents),
            ),
        ];
    }

    private function key(string $contents): string
    {
        if (1 === preg_match('/(?:\r\n|\n|\r)\z/', $contents)) {
            return 'extra-final-newlines';
        }

        return 'missing-final-newline';
    }
}
