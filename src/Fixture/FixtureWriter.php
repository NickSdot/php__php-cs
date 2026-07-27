<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use InternalsCS\Support\FileSystem;

use function is_dir;
use function is_file;
use function rmdir;

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

    public function remove(FixtureSource $source, string $fixturesDir): bool
    {
        $fixtureDir = $fixturesDir . DIRECTORY_SEPARATOR . $this->caseName->fromFixtureSource($source);

        if (!is_dir($fixtureDir)) {
            return false;
        }

        $fixtureFiles = new FixturePairFiles($fixtureDir);
        $this->files->deleteFileIfExists($fixtureFiles->oldPath(), 'old fixture');
        $this->files->deleteFileIfExists($fixtureFiles->newPath(), 'new fixture');
        $this->files->deleteFileIfExists($fixtureFiles->diffPath(), 'fixture diff');

        if (!rmdir($fixtureDir)) {
            throw new \RuntimeException('Cannot delete rejected fixture directory: ' . $fixtureDir);
        }

        return true;
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
