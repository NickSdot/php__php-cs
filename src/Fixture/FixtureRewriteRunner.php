<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

interface FixtureRewriteRunner
{
    /** @return array{changed: bool, failed: bool, output: string, failure: string|null} */
    public function printFile(string $path): array;
}
