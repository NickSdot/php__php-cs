<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use function mb_trim;
use function preg_replace;

final readonly class FixtureCaseName
{
    public function fromCandidate(FixtureCandidate $candidate): string
    {
        return $this->fromSourcePath($candidate->relativePath());
    }

    public function fromFixtureSource(FixtureSource $source): string
    {
        return $this->fromSourcePath($source->relativePath);
    }

    public function fromSourcePath(string $sourcePath): string
    {
        $base = preg_replace('~\.[A-Za-z0-9]+$~', '', $sourcePath);

        return $this->slug((string) $base);
    }

    private function slug(string $value): string
    {
        $base = preg_replace('~[^A-Za-z0-9_]+~', '_', $value);

        return mb_trim((string) $base, '_');
    }
}
