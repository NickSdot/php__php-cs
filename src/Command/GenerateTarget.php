<?php

declare(strict_types=1);

namespace InternalsCS\Command;

use InternalsCS\Console\ConsoleIo;
use InternalsCS\Fixture\FixtureGenerationResult;
use InternalsCS\Fixture\FixtureGenerator;
use InternalsCS\Fixture\FixtureRewriteRunner;

interface GenerateTarget
{
    public function name(): string;

    public function description(): string;

    /** @return list<string> */
    public function sourceExtensions(): array;

    public function defaultFixturesDir(string $toolRoot): string;

    public function defaultReportsDir(string $toolRoot): string;

    public function checkRuntime(ConsoleIo $io): bool;

    public function requiresPhpTestRuntime(): bool;

    public function generator(): FixtureGenerator;

    public function rewriteRunner(GenerateOptions $options): FixtureRewriteRunner;

    public function printResult(FixtureGenerationResult $result, ConsoleIo $io): int;
}
