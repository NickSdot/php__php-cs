<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Generation;

use InternalsCS\Fixers\ExceptionOutput\Analysis\Classification;
use InternalsCS\Fixers\ExceptionOutput\Analysis\ExpectedOutputEvidence;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputParts;
use InternalsCS\Fixture\FixtureCandidate;

final readonly class Candidate implements FixtureCandidate
{
    public string $fixtureCaseKey;

    public function __construct(
        public string $sourcePath,
        public string $relativePath,
        public int $line,
        public string $statement,
        public OutputParts $parts,
        public string $fixtureKey,
        public Classification $classification,
        ?string $fixtureCaseKey = null,
        public string $catchVariable = 'e',
        /** @var list<string> */
        public array $catchTypes = [],
        private ExpectedOutputEvidence $evidence = new ExpectedOutputEvidence(),
    ) {
        $this->fixtureCaseKey = $fixtureCaseKey ?? $fixtureKey;
    }

    public function isRepresentedInExpectedOutput(string $expectedOutput): bool
    {
        return $this->evidence->contains($expectedOutput, $this->parts);
    }
}
