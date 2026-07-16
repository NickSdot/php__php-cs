<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis;

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
        $payload = $family->value . '|' . $parts->fingerprintPayload($this->trash);
        $fingerprint = new Fingerprint($family, $payload);

        return new Classification(
            family: $family,
            safety: $safety,
            fingerprint: $fingerprint,
            reason: $this->reason($safety),
            partsSummary: $parts->summary($this->trash),
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

        return ClassificationSafety::Canonicalizable;
    }

    private function reason(ClassificationSafety $safety): string
    {
        return match ($safety) {
            ClassificationSafety::Canonicalizable => 'safe candidate for a canonical exception-output family',
            ClassificationSafety::AlreadyCanonical => 'already canonical',
            ClassificationSafety::DescriptiveContext => 'contains descriptive context that must not be dropped',
            ClassificationSafety::MixedSemantics => 'mixes exception output with other output semantics',
            ClassificationSafety::NoExceptionMessage => 'does not contain an exception message call',
            ClassificationSafety::UnsupportedExpression => 'contains an unsupported expression shape',
        };
    }
}
