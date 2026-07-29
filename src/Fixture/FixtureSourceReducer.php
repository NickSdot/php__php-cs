<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

interface FixtureSourceReducer
{
    public function reduce(FixtureSource $source, FixtureRewriteRunner $runner): ?string;
}
