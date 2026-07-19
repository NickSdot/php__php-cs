<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Fixing\Rules;

use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputFamily;
use InternalsCS\Fixers\ExceptionOutput\Fixing\Statement;

final readonly class LocationOutputRule extends SingleStatementOutputRule
{
    protected function families(): array
    {
        return [
            OutputFamily::MessageOnly,
            OutputFamily::ClassMessageLocation,
        ];
    }

    protected function accepts(Statement $statement): bool
    {
        return $this->hasLocation($statement);
    }
}
