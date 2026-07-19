<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Generation;

use InternalsCS\Fixers\ExceptionOutput\Analysis\Classification;
use InternalsCS\Fixers\ExceptionOutput\Analysis\ExpectedOutputEvidence;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputParts;
use InternalsCS\Fixture\FixtureCandidate;

final readonly class Candidate implements FixtureCandidate
{
    public function __construct(
        public string $sourcePath,
        public string $relativePath,
        public int $line,
        public string $statement,
        public OutputParts $parts,
        public string $fixtureKey,
        public Classification $classification,
        private ExpectedOutputEvidence $evidence = new ExpectedOutputEvidence(),
    ) {}

    public function isRepresentedInExpectedOutput(string $expectedOutput): bool
    {
        return $this->evidence->contains($expectedOutput, $this->parts);
    }
}
