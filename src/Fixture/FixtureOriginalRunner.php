<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

interface FixtureOriginalRunner
{
    /** @return array{passed: bool, failure: string|null} */
    public function runOriginalFile(string $path): array;
}
