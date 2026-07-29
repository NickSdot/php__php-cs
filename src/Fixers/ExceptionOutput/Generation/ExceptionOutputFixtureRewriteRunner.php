<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Generation;

use InternalsCS\Fixers\ExceptionOutput\ExceptionOutputFixer;
use InternalsCS\Fixers\FinalNewline\FinalNewlineFixer;
use InternalsCS\Fixture\FixtureOriginalRunner;
use InternalsCS\Fixture\FixtureRewriteRunner;
use InternalsCS\PhpSrcTestStyle\PhptFixtureRewriteRunner;

final readonly class ExceptionOutputFixtureRewriteRunner implements FixtureRewriteRunner, FixtureOriginalRunner
{
    private PhptFixtureRewriteRunner $probeRunner;

    private PhptFixtureRewriteRunner $targetRunner;

    public function __construct(string $phpSrcDir)
    {
        $this->probeRunner = new PhptFixtureRewriteRunner(
            phpSrcDir: $phpSrcDir,
            fixerClasses: [ExceptionOutputFixer::class],
        );
        $this->targetRunner = new PhptFixtureRewriteRunner(
            phpSrcDir: $phpSrcDir,
            fixerClasses: [ExceptionOutputFixer::class, FinalNewlineFixer::class],
        );
    }

    public function printFile(string $path): array
    {
        // Only exception-output changes decide whether this is a valid exception-output fixture.
        // Once accepted, final-newline is allowed to normalise the generated target fixture.
        $probe = $this->probeRunner->printFile($path);

        if ($probe['failed'] || !$probe['changed']) {
            return $probe;
        }

        return $this->targetRunner->printFile($path);
    }

    public function runOriginalFile(string $path): array
    {
        return $this->probeRunner->runOriginalFile($path);
    }
}
