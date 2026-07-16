<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Validation;

use InternalsCS\FixerRunner;
use InternalsCS\Fixture\FixtureRewriteRunner;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalFixer;

final readonly class PhptFixtureRewriteRunner implements FixtureRewriteRunner
{
    private FixerRunner $runner;

    public function __construct(string $phpSrcDir)
    {
        $this->runner = new FixerRunner($phpSrcDir, [
            CanonicalFixer::class,
        ]);
    }

    public function printFile(string $path): array
    {
        return $this->runner->printFile($path);
    }
}
