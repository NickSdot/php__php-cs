<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputFamily;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Statement;

final readonly class MessageOnlyOutputRule extends SingleStatementOutputRule
{
    protected function families(): array
    {
        return [OutputFamily::MessageOnly];
    }

    protected function accepts(Statement $statement): bool
    {
        return !$this->hasLocation($statement);
    }
}
