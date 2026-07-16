<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

interface FixtureScanner
{
    /**
     * @param list<string> $files
     * @return list<FixtureCandidate>
     */
    public function scan(array $files, string $rootDir): array;
}
