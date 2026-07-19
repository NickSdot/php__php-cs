<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Generation;

use InternalsCS\Fixture\FixtureScanner;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\Classifier;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\StatementWindowFinder;
use InternalsCS\Support\Paths;

use function array_push;
use function file_get_contents;
use function str_contains;

final readonly class Scanner implements FixtureScanner
{
    public function __construct(
        private PhptSections $sections = new PhptSections(),
        private StatementWindowFinder $windows = new StatementWindowFinder(),
        private Classifier $classifier = new Classifier(),
        private Paths $paths = new Paths(),
    ) {}

    /**
     * @param list<string> $files
     * @return list<Candidate>
     */
    public function scan(array $files, string $rootDir): array
    {
        $candidates = [];

        foreach ($files as $file) {
            array_push($candidates, ...$this->candidates($file, $rootDir));
        }

        return $candidates;
    }

    /** @return list<Candidate> */
    private function candidates(string $file, string $rootDir): array
    {
        $contents = file_get_contents($file);

        if (false === $contents || !str_contains($contents, 'getMessage')) {
            return [];
        }

        $code = $this->sections->code($contents);

        if (null === $code) {
            return [];
        }

        $relativePath = $this->paths->relative($file, $rootDir);
        $candidates = [];

        foreach ($this->windows->find($code->contents) as $window) {
            $classification = $this->classifier->classify($window);
            $line = $code->startLine + $window->startLine - 1;

            $candidates[] = new Candidate(
                sourcePath: $file,
                relativePath: $relativePath,
                line: $line,
                statement: $window->statement,
                parts: $window->parts,
                key: $classification->fingerprint->id,
                classification: $classification,
            );
        }

        return $candidates;
    }
}
