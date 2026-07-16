<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

interface FixtureCandidate
{
    public function sourcePath(): string;

    public function relativePath(): string;

    public function fixtureKey(): string;
}
