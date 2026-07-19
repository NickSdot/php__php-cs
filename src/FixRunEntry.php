<?php

declare(strict_types=1);

namespace InternalsCS;

final readonly class FixRunEntry
{
    public function __construct(
        public FixRunStatus $status,
        public string $file,
        public string $fixer,
        public string $location,
        public ?string $reason = null,
    ) {}

    public function consoleLine(): string
    {
        $line = $this->file . ': ' . $this->fixer . $this->locationLabel() . ' ' . $this->status->label();

        if (FixRunStatus::Skipped === $this->status && null !== $this->reason) {
            $line .= ': ' . $this->reason;
        }

        return $line;
    }

    private function locationLabel(): string
    {
        return '' === $this->location ? '' : ' (' . $this->location . ')';
    }
}
