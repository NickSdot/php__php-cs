<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

interface ExpectedOutputFixtureCandidate extends FixtureCandidate
{
    public function isRepresentedInExpectedOutput(string $expectedOutput): bool;
}
