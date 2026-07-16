<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

final readonly class FixtureValidationOptions
{
    /** @param list<string> $cases */
    public function __construct(
        public string $fixturesDir,
        public array $cases,
        public FixtureRewriteRunner $runner,
        public bool $update,
        public bool $failFast,
        public bool $refreshPairs = false,
    ) {}
}
