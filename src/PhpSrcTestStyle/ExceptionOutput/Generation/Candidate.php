<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Generation;

use InternalsCS\Fixture\FixtureCandidate;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\Classification;

final readonly class Candidate implements FixtureCandidate
{
    public function __construct(
        public string $sourcePath,
        public string $relativePath,
        public int $line,
        public string $statement,
        public string $key,
        public Classification $classification,
        public string $expectedSection,
        public string $context,
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
}
