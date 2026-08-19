<?php

declare(strict_types=1);

namespace InternalsCS;

use InternalsCS\Fixers\ExceptionOutput\ExceptionOutputFixer;
use InternalsCS\Fixers\ExceptionOutput\Fixing\ExpectedOutputUpdater;
use InternalsCS\Fixers\ExceptionOutput\Fixing\OutputRewriteImpact;
use InternalsCS\Fixers\ExceptionOutput\Fixing\OutputRewritePlanner;

final readonly class FixerFactory
{
    public function __construct(
        private OutputRewritePlanner $exceptionOutputPlanner = new OutputRewritePlanner(),
        private ExpectedOutputUpdater $expectedOutputUpdater = new ExpectedOutputUpdater(),
        private OutputRewriteImpact $outputRewriteImpact = new OutputRewriteImpact(),
    ) {}

    /** @param class-string<Fixer> $fixerClass */
    public function create(string $fixerClass, FixerRunOptions $options): Fixer
    {
        if (ExceptionOutputFixer::class !== $fixerClass) {
            return new $fixerClass();
        }

        return new ExceptionOutputFixer(
            planner: $this->exceptionOutputPlanner,
            expectedOutput: $this->expectedOutputUpdater,
            impact: $this->outputRewriteImpact,
            skipNormalizationOnly: $options->skipNormalizationOnly,
        );
    }
}
