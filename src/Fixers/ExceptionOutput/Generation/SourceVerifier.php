<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Generation;

use InternalsCS\Fixture\FixtureCaseName;
use InternalsCS\Fixture\FixtureSource;
use InternalsCS\Fixture\FixtureSourceRunVerifier;
use InternalsCS\Fixture\FixtureSourceVerification;
use InternalsCS\Fixture\FixtureSourceVerifier;
use InternalsCS\PhpSrcTestStyle\PhptFile;

use function dirname;
use function is_file;

final readonly class SourceVerifier implements FixtureSourceVerifier
{
    public function __construct(
        private FixtureSourceRunVerifier $runVerifier = new FixtureSourceRunVerifier(),
        private FixtureCaseName $caseName = new FixtureCaseName(),
    ) {}

    public function canSelect(
        FixtureSource $source,
        FixtureSourceVerification $verification,
    ): bool {
        if (!$this->runVerifier->canSelect($source, $verification)) {
            return false;
        }

        if ($this->fixtureExists($source, $verification->fixturesDir)) {
            return true;
        }

        $expected = $this->expectedOutput($source);

        if (null === $expected) {
            return false;
        }

        foreach ($source->candidates as $candidate) {
            if (!$candidate instanceof Candidate) {
                throw new \LogicException('Exception-output source verifier received a non exception-output candidate');
            }

            if (!$candidate->isRepresentedInExpectedOutput($expected)) {
                return false;
            }
        }

        return $this->runVerifier->canRewrite($source, $verification);
    }

    private function fixtureExists(FixtureSource $source, string $fixturesDir): bool
    {
        return is_file($fixturesDir . DIRECTORY_SEPARATOR . $this->caseName->fromFixtureSource($source) . '/old.phpt');
    }

    private function expectedOutput(FixtureSource $source): ?string
    {
        $file = new PhptFile($source->sourcePath, dirname($source->sourcePath));
        $section = $file->expectedSectionName();

        return null === $section ? null : $file->getSection($section);
    }
}
