<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Fixing;

use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputExpectation;

final readonly class RewriteContext
{
    public function __construct(
        public string $catchVariable,
        /** @var list<string> */
        public array $catchTypes,
        public Statement $statement,
        public ?Statement $nextStatement,
        public OutputExpectation $expectation = new OutputExpectation(),
    ) {}
}
