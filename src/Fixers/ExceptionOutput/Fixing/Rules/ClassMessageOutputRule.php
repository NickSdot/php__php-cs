<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Fixing\Rules;

use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputFamily;
use InternalsCS\Fixers\ExceptionOutput\Fixing\Statement;

final readonly class ClassMessageOutputRule extends SingleStatementOutputRule
{
    protected function families(): array
    {
        return [OutputFamily::ClassMessage];
    }

    protected function accepts(Statement $statement): bool
    {
        return !$this->hasLocation($statement);
    }
}
