<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Fixing;

use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputExpectation;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputExpectationAnalyzer;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputParts;

final readonly class RewriteScope
{
    public function __construct(
        public string $code,
        public int $offsetDelta,
        private ?string $expectedOutput,
        private OutputExpectationAnalyzer $expectations = new OutputExpectationAnalyzer(),
    ) {}

    public function expectation(OutputParts $output): OutputExpectation
    {
        return $this->expectations->analyze($output, $this->expectedOutput);
    }
}
