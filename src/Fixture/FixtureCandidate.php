<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

interface FixtureCandidate
{
    public string $sourcePath { get; }

    public string $relativePath { get; }

    public string $fixtureKey { get; }
}
