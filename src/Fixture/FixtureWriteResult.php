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

    public static function failure(string $message): self
    {
        return new self(
            createdOld: false,
            updatedNew: false,
            verifiedPair: false,
            oldOnly: false,
            failure: $message,
        );
    }
}
