<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use function is_file;

final readonly class FixturePairFiles
{
    public function __construct(
        private string $dir,
    ) {}

    public function oldPath(): string
    {
        return $this->dir . '/old.phpt';
    }

    public function newPath(): string
    {
        return $this->dir . '/new.phpt';
    }

    public function diffPath(): string
    {
        return $this->dir . '/ran.diff';
    }

    public function hasOld(): bool
    {
        return is_file($this->oldPath());
    }

    public function hasNew(): bool
    {
        return is_file($this->newPath());
    }

    public function hasDiff(): bool
    {
        return is_file($this->diffPath());
    }

    public function containsFixtureFiles(): bool
    {
        return $this->hasOld() || $this->hasNew() || $this->hasDiff();
    }
}
