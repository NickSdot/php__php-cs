<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Analysis;

final readonly class Classification
{
    public Fingerprint $fixtureFingerprint;

    public function __construct(
        public OutputFamily $family,
        public ClassificationSafety $safety,
        public Fingerprint $fingerprint,
        ?Fingerprint $fixtureFingerprint = null,
    ) {
        $this->fixtureFingerprint = $fixtureFingerprint ?? $fingerprint;
    }
}
