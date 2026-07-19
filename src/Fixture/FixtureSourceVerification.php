<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

final readonly class FixtureSourceVerification
{
    public function __construct(
        public string $fixturesDir,
        public FixtureRewriteRunner $runner,
        public ?string $rewriteRoot,
    ) {}
}
