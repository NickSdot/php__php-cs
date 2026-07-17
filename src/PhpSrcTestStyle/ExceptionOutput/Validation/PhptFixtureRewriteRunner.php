<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Validation;

use InternalsCS\FixerRunner;
use InternalsCS\Fixture\FixtureOriginalRunner;
use InternalsCS\Fixture\FixtureRewriteRunner;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalFixer;
use InternalsCS\PhpSrcTestStyle\PhptFile;

final readonly class PhptFixtureRewriteRunner implements FixtureRewriteRunner, FixtureOriginalRunner
{
    private FixerRunner $runner;

    public function __construct(
        private string $phpSrcDir,
    ) {
        $this->runner = new FixerRunner($phpSrcDir, [
            CanonicalFixer::class,
        ]);
    }

    public function printFile(string $path): array
    {
        return $this->runner->printFile($path);
    }

    public function runOriginalFile(string $path): array
    {
        $file = new PhptFile($path, $this->phpSrcDir);
        $run = $file->run();
        $file->cleanupArtifacts();

        if ('PASS' === $run['status']) {
            return [
                'passed' => true,
                'failure' => null,
            ];
        }

        return [
            'passed' => false,
            'failure' => $run['status'],
        ];
    }
}
