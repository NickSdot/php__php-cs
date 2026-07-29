<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Generation;

use InternalsCS\Fixers\ExceptionOutput\Analysis\Classifier;
use InternalsCS\Fixers\ExceptionOutput\Analysis\StatementWindowFinder;
use InternalsCS\Fixers\ExceptionOutput\Fixing\OutputRewritePlanner;
use InternalsCS\PhpSrcTestStyle\PhptSections;
use InternalsCS\SourceFile;

use function mb_substr;
use function str_contains;

final readonly class CandidateCollector
{
    public function __construct(
        private PhptSections $sections = new PhptSections(),
        private StatementWindowFinder $windows = new StatementWindowFinder(),
        private Classifier $classifier = new Classifier(),
        private ExpectedOutputShape $expectedOutput = new ExpectedOutputShape(),
        private OutputRewritePlanner $planner = new OutputRewritePlanner(),
        private bool $requireExpectedOutputEvidence = true,
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
        $rewriteWindows = $this->rewriteWindows($code->contents);
        $candidates = [];

        foreach ($this->windows->find($code->contents) as $window) {
            if (!isset($rewriteWindows[$window->startOffset . ':' . $window->endOffset])) {
                continue;
            }

            $classification = $this->classifier->classify($window);
            $line = $code->startLine + $window->startLine - 1;

            $candidate = new Candidate(
                sourcePath: $source->path,
                relativePath: $source->relativePath(),
                line: $line,
                statement: $window->statement,
                parts: $window->parts,
                fixtureKey: $classification->fingerprint->id . $this->expectedOutput->key($window, $code, $expected),
                classification: $classification,
                fixtureCaseKey: $classification->fixtureFingerprint->id . $this->expectedOutput->key($window, $code, $expected),
                catchVariable: $window->catchVariable ?? 'e',
                catchTypes: $window->catchTypes,
            );

            if ($this->requireExpectedOutputEvidence && (null === $expected || !$candidate->isRepresentedInExpectedOutput($expected->contents))) {
                continue;
            }

            $candidates[] = $candidate;
        }

        return $candidates;
    }

    /** @return array<string, true> */
    private function rewriteWindows(string $code): array
    {
        $windows = [];

        foreach ($this->planner->plans($code) as $plan) {
            $current = mb_substr($code, $plan->startOffset, $plan->endOffset - $plan->startOffset, '8bit');

            if ($current === $plan->replacement) {
                continue;
            }

            $windows[$plan->startOffset . ':' . $plan->endOffset] = true;
        }

        return $windows;
    }
}
