<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Generation;

enum PreviousSeparatorContext: string
{
    case None = 'none';
    case Moved = 'moved';
    case BlockedByTryOutput = 'blocked-by-try-output';
}
