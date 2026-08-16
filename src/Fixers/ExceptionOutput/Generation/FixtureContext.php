<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Generation;

final readonly class FixtureContext
{
    public function __construct(
        public PreviousSeparatorContext $previousSeparator = PreviousSeparatorContext::None,
        public bool $followingNewlineMoved = false,
        public bool $followingTraceOutput = false,
    ) {}

    public function key(): string
    {
        if (PreviousSeparatorContext::None === $this->previousSeparator
            && !$this->followingNewlineMoved
            && !$this->followingTraceOutput
        ) {
            return '';
        }

        return '|context:previous-' . $this->previousSeparator->value
            . ':following-' . ($this->followingNewlineMoved ? 'moved' : 'none')
            . ':trace-' . ($this->followingTraceOutput ? 'present' : 'none');
    }
}
