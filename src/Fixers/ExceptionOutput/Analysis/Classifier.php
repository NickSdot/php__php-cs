<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Analysis;

final readonly class Classifier
{
    public function __construct(
        private TrashLiteralPolicy $trash = new TrashLiteralPolicy(),
        private DescriptiveContextPolicy $context = new DescriptiveContextPolicy(),
    ) {}

    public function classify(Window $window): Classification
    {
        $parts = $window->parts;
        $family = $this->family($parts);
        $safety = $this->safety($parts);
        $fingerprint = new Fingerprint($family, $family->value . '|' . $parts->fingerprintPayload($this->trash));
        $fixtureFingerprint = new Fingerprint(
            $family,
            $family->value . '|' . $safety->value . '|' . $parts->fixtureFingerprintPayload($this->trash),
        );

        return new Classification(
            family: $family,
            safety: $safety,
            fingerprint: $fingerprint,
            fixtureFingerprint: $fixtureFingerprint,
        );
    }

    private function family(OutputParts $parts): OutputFamily
    {
        $hasClass = $parts->has(OutputPartKind::ExceptionClass);
        $hasMessage = $parts->has(OutputPartKind::ExceptionMessage);
        $hasFile = $parts->has(OutputPartKind::ExceptionFile);
        $hasLine = $parts->has(OutputPartKind::ExceptionLine);

        if (!$hasMessage) {
            return OutputFamily::Unknown;
        }

        if ($hasFile || $hasLine) {
            return $hasClass ? OutputFamily::ClassMessageLocation : OutputFamily::MessageOnly;
        }

        if ($hasClass) {
            return OutputFamily::ClassMessage;
        }

        return OutputFamily::MessageOnly;
    }

    private function safety(OutputParts $parts): ClassificationSafety
    {
        if (!$parts->has(OutputPartKind::ExceptionMessage)) {
            return ClassificationSafety::NoExceptionMessage;
        }

        if ($parts->hasDescriptiveContext($this->context)) {
            return ClassificationSafety::DescriptiveContext;
        }

        if ($parts->hasUnknown()) {
            return ClassificationSafety::UnsupportedExpression;
        }

        return ClassificationSafety::Fixable;
    }

}
