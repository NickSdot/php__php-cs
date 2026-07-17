<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use InternalsCS\Support\FileSystem;

use function is_file;

final readonly class FixtureWriter
{
    public function __construct(
        private FixtureCaseName $caseName = new FixtureCaseName(),
        private FileSystem $files = new FileSystem(),
    ) {}

    public function write(FixtureSource $source, string $fixturesDir): FixtureWriteResult
    {
        $fixtureDir = $fixturesDir . DIRECTORY_SEPARATOR . $this->caseName->fromFixtureSource($source);
        $fixtureFiles = new FixturePairFiles($fixtureDir);

        $this->files->ensureDirectory($fixtureDir, 'fixture directory');

        $createdOld = $this->ensureOldFixture($source, $fixtureFiles);

        return new FixtureWriteResult(
            createdOld: $createdOld,
            updatedNew: false,
            verifiedPair: false,
            oldOnly: true,
            failure: null,
        );
    }

    private function ensureOldFixture(FixtureSource $source, FixturePairFiles $fixtureFiles): bool
    {
        $oldPath = $fixtureFiles->oldPath();

        if (is_file($oldPath)) {
            return false;
        }

        $contents = $this->files->read($source->sourcePath, 'source file');
        $this->files->write($oldPath, $contents, 'fixture');

        return true;
    }
}
