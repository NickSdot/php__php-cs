<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Generation;

use InternalsCS\Fixers\ExceptionOutput\Analysis\Classifier;
use InternalsCS\Fixers\ExceptionOutput\Analysis\StatementWindowFinder;
use InternalsCS\SourceFile;

use function str_contains;

final readonly class CandidateCollector
{
    public function __construct(
        private PhptSections $sections = new PhptSections(),
        private StatementWindowFinder $windows = new StatementWindowFinder(),
        private Classifier $classifier = new Classifier(),
        private ExpectedOutputShape $expectedOutput = new ExpectedOutputShape(),
    ) {}

    /** @return list<Candidate> */
    public function collect(SourceFile $source): array
    {
        if (!str_contains($source->contents, 'getMessage')) {
            return [];
        }

        $code = $this->sections->code($source->contents);

        if (null === $code) {
            return [];
        }

        $expected = $this->sections->expected($source->contents);
        $candidates = [];

        foreach ($this->windows->find($code->contents) as $window) {
            $classification = $this->classifier->classify($window);
            $line = $code->startLine + $window->startLine - 1;

            $candidates[] = new Candidate(
                sourcePath: $source->path,
                relativePath: $source->relativePath(),
                line: $line,
                statement: $window->statement,
                parts: $window->parts,
                fixtureKey: $classification->fingerprint->id . $this->expectedOutput->key($window, $code, $expected),
                classification: $classification,
            );
        }

        return $candidates;
    }
}
