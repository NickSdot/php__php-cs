<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Generation;

use InternalsCS\Fixture\ExpectedOutputFixtureCandidate;
use InternalsCS\Fixture\FixtureCaseName;
use InternalsCS\Fixture\FixtureGenerationOptions;
use InternalsCS\Fixture\FixtureSource;
use InternalsCS\Fixture\FixtureSourceRunVerifier;
use InternalsCS\Fixture\FixtureSourceVerifier;
use InternalsCS\PhpSrcTestStyle\PhptFile;

use function dirname;
use function is_file;
use function str_starts_with;

final readonly class SourceVerifier implements FixtureSourceVerifier
{
    public function __construct(
        private FixtureSourceVerifier $runVerifier = new FixtureSourceRunVerifier(),
        private FixtureCaseName $caseName = new FixtureCaseName(),
    ) {}

    public function canSelect(FixtureSource $source, FixtureGenerationOptions $options): bool
    {
        if (!$this->runVerifier->canSelect($source, $options)) {
            return false;
        }

        if ($this->isManualFixture($source)) {
            return true;
        }

        if ($this->fixtureExists($source, $options)) {
            return true;
        }

        $expected = $this->expectedOutput($source);

        if (null === $expected) {
            return false;
        }

        foreach ($source->candidates as $candidate) {
            if (!$candidate instanceof ExpectedOutputFixtureCandidate) {
                continue;
            }

            if (!$candidate->isRepresentedInExpectedOutput($expected)) {
                return false;
            }
        }

        return true;
    }

    private function isManualFixture(FixtureSource $source): bool
    {
        return str_starts_with($source->relativePath, 'manual_');
    }

    private function fixtureExists(FixtureSource $source, FixtureGenerationOptions $options): bool
    {
        return is_file($options->fixturesDir . DIRECTORY_SEPARATOR . $this->caseName->fromFixtureSource($source) . '/old.phpt');
    }

    private function expectedOutput(FixtureSource $source): ?string
    {
        $file = new PhptFile($source->sourcePath, dirname($source->sourcePath));
        $section = $file->expectedSectionName();

        return null === $section ? null : $file->getSection($section);
    }
}
