<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

final readonly class FixtureWriteResult
{
    public function __construct(
        public bool $createdOld,
        public bool $updatedNew,
        public bool $verifiedPair,
        public bool $oldOnly,
        public ?string $failure,
    ) {}
}
