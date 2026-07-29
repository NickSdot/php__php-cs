<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\FinalNewline\Generation;

use InternalsCS\Fixture\FixtureCandidate;

final readonly class Candidate implements FixtureCandidate
{
    public string $fixtureCaseKey;

    public function __construct(
        public string $sourcePath,
        public string $relativePath,
        public string $fixtureKey,
        ?string $fixtureCaseKey = null,
    ) {
        $this->fixtureCaseKey = $fixtureCaseKey ?? $fixtureKey;
    }
}
