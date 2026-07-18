<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputFamily;

use function count;

final readonly class LeadingSeparatorOutput
{
    public function __construct(
        private CanonicalRewriteSafety $safety = new CanonicalRewriteSafety(),
        private OutputPartMatcher $parts = new OutputPartMatcher(),
    ) {}

    public function matches(Statement $statement, string $catchVariable): bool
    {
        $parts = $statement->parts->parts;

        if (count($parts) < 3 || !$this->parts->isNewline($parts[0])) {
            return false;
        }

        if (!$this->parts->isExceptionMessage($parts[1], $catchVariable)) {
            return false;
        }

        if (!$this->parts->onlyNewlinesAfter($parts, 2)) {
            return false;
        }

        return $this->safety->canRewrite($statement, $catchVariable, OutputFamily::MessageOnly);
    }
}
