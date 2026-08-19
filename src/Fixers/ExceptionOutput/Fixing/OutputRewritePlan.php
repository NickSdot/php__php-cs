<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Fixing;

use InternalsCS\TextEdit;

final readonly class OutputRewritePlan
{
    /**
     * @param list<TextEdit> $outputEdits
     * @param list<TextEdit> $catchTypeEdits
     */
    public function __construct(
        public array $outputEdits,
        public array $catchTypeEdits,
    ) {}

    /** @return list<TextEdit> */
    public function all(): array
    {
        return [...$this->outputEdits, ...$this->catchTypeEdits];
    }
}
