<?php

declare(strict_types=1);

namespace InternalsCS;

enum FixRunStatus: string
{
    case Fixed = 'fixed';
    case Skipped = 'skipped';
    case NeedsChanges = 'needs_changes';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'fixed',
            self::Skipped => 'skipped',
            self::NeedsChanges => 'needs changes',
        };
    }
}
