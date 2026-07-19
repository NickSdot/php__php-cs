<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle;

use InternalsCS\FixerRegistry;
use InternalsCS\FixerRunner;
use InternalsCS\Fixture\FixtureOriginalRunner;
use InternalsCS\Fixture\FixtureRewriteRunner;

final readonly class PhptFixtureRewriteRunner implements FixtureRewriteRunner, FixtureOriginalRunner
{
    private FixerRunner $runner;

    /** @param list<class-string<\InternalsCS\Fixer>>|null $fixerClasses */
    public function __construct(
        private string $phpSrcDir,
        ?array $fixerClasses = null,
    ) {
        $this->runner = new FixerRunner($phpSrcDir, $fixerClasses ?? new FixerRegistry()->all());
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
