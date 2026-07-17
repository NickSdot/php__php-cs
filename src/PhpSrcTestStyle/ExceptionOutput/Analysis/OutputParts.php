<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis;

use function array_any;
use function implode;
use function sha1;
use function str_replace;

final readonly class OutputParts
{
    /** @param list<OutputPart> $parts */
    public function __construct(
        public array $parts,
        public string $shape,
        private MarkerPrefixPolicy $markers = new MarkerPrefixPolicy(),
    ) {}

    public function has(OutputPartKind $kind): bool
    {
        return array_any($this->parts, fn($part) => $part->kind === $kind);
    }

    public function hasUnknown(): bool
    {
        return $this->has(OutputPartKind::Unknown);
    }

    public function hasDescriptiveContext(DescriptiveContextPolicy $policy): bool
    {
        foreach ($this->parts as $part) {
            if (OutputPartKind::Literal !== $part->kind) {
                continue;
            }

            if ($policy->isDescriptiveLiteral($part->value)) {
                return true;
            }
        }

        return false;
    }

    public function fingerprintPayload(TrashLiteralPolicy $trash): string
    {
        $payload = [$this->shape];

        foreach ($this->parts as $part) {
            $payload[] = $this->fingerprintPart($part, $trash);
        }

        return implode('|', $payload);
    }

    public function summary(TrashLiteralPolicy $trash): string
    {
        $parts = [];

        foreach ($this->parts as $part) {
            $parts[] = $this->fingerprintPart($part, $trash);
        }

        return implode(' ', $parts);
    }

    private function fingerprintPart(OutputPart $part, TrashLiteralPolicy $trash): string
    {
        return match ($part->kind) {
            OutputPartKind::Literal => $this->literalFingerprint($part, $trash),
            OutputPartKind::ExceptionClass, OutputPartKind::Unknown => $this->sourceAwareFingerprint($part),
            default => $part->kind->value,
        };
    }

    private function literalFingerprint(OutputPart $part, TrashLiteralPolicy $trash): string
    {
        if (': ' === $part->value) {
            return 'literal:colon_separator';
        }

        if (' in ' === $part->value) {
            return 'literal:in_separator';
        }

        if (' on line ' === $part->value) {
            return 'literal:on_line_separator';
        }

        if ($this->markers->isBracketedNumeric($part->value)) {
            return 'literal:marker:bracketed_numeric';
        }

        if ($this->markers->isErrorNumber($part->value)) {
            return 'literal:marker:error_number';
        }

        if ($this->markers->isVariableMarkerSeparator($part->value)) {
            return 'literal:marker:variable_separator';
        }

        $trashLabel = $trash->label($part->value);

        if (null !== $trashLabel) {
            return 'literal:trash:' . str_replace(' ', '_', $trashLabel);
        }

        return 'literal:' . sha1($part->value);
    }

    private function sourceAwareFingerprint(OutputPart $part): string
    {
        if (null === $part->source) {
            return $part->kind->value;
        }

        return $part->kind->value . '(' . $part->source . ')';
    }
}
