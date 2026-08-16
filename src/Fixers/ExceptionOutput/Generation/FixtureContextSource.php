<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Generation;

use InternalsCS\Fixers\ExceptionOutput\Analysis\Window;
use InternalsCS\TextEdit;

final readonly class FixtureContextSource
{
    /** @param list<TextEdit> $plans */
    public function __construct(
        public string $code,
        public int $offsetDelta,
        public Window $window,
        public array $plans,
    ) {}
}
