<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

interface FixtureSourceVerifier
{
    public function canSelect(
        FixtureSource $source,
        FixtureSourceVerification $verification,
    ): bool;
}
