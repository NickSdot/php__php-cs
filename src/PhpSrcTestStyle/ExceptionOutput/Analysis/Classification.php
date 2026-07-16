<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis;

final readonly class Classification
{
    public function __construct(
        public OutputFamily $family,
        public ClassificationSafety $safety,
        public Fingerprint $fingerprint,
        public string $reason,
        public string $partsSummary,
    ) {}

    public function canAttemptRewrite(): bool
    {
        return ClassificationSafety::Canonicalizable === $this->safety
            || ClassificationSafety::AlreadyCanonical === $this->safety;
    }
}
