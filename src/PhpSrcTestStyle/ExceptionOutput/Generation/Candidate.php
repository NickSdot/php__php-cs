<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Generation;

use InternalsCS\Fixture\ExpectedOutputFixtureCandidate;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\Classification;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\ExpectedOutputEvidence;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputParts;

final readonly class Candidate implements ExpectedOutputFixtureCandidate
{
    public function __construct(
        public string $sourcePath,
        public string $relativePath,
        public int $line,
        public string $statement,
        public OutputParts $parts,
        public string $key,
        public Classification $classification,
        private ExpectedOutputEvidence $evidence = new ExpectedOutputEvidence(),
    ) {}

    public function sourcePath(): string
    {
        return $this->sourcePath;
    }

    public function relativePath(): string
    {
        return $this->relativePath;
    }

    public function fixtureKey(): string
    {
        return $this->key;
    }

    public function isRepresentedInExpectedOutput(string $expectedOutput): bool
    {
        return $this->evidence->contains($expectedOutput, $this->parts);
    }
}
