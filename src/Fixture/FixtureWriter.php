<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use InternalsCS\Support\FileSystem;

use function dirname;
use function is_dir;
use function is_file;
use function realpath;
use function rmdir;
use function str_ends_with;
use function str_starts_with;

final readonly class FixtureWriter
{
    public function __construct(
        private FixtureCaseName $caseName = new FixtureCaseName(),
        private FileSystem $files = new FileSystem(),
    ) {}

    public function write(FixtureSource $source, string $fixturesDir, ?string $oldContents = null): FixtureWriteResult
    {
        $fixtureDir = $fixturesDir . DIRECTORY_SEPARATOR . $this->caseName->fromFixtureSource($source);
        $fixtureFiles = new FixturePairFiles($fixtureDir);

        $this->files->ensureDirectory($fixtureDir, 'fixture directory');

        $createdOld = $this->ensureOldFixture($source, $fixtureFiles, $oldContents);

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
        $fixtureDir = $this->fixtureDir($source, $fixturesDir);

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

    private function fixtureDir(FixtureSource $source, string $fixturesDir): string
    {
        if (
            str_starts_with($source->sourcePath, $fixturesDir . DIRECTORY_SEPARATOR)
            && str_ends_with($source->sourcePath, DIRECTORY_SEPARATOR . 'old.phpt')
        ) {
            return dirname($source->sourcePath);
        }

        return $fixturesDir . DIRECTORY_SEPARATOR . $this->caseName->fromFixtureSource($source);
    }

    private function ensureOldFixture(FixtureSource $source, FixturePairFiles $fixtureFiles, ?string $oldContents): bool
    {
        $oldPath = $fixtureFiles->oldPath();

        if (is_file($oldPath) && $this->sameFile($oldPath, $source->sourcePath)) {
            return false;
        }

        $this->files->write($oldPath, $oldContents ?? $this->files->read($source->sourcePath, 'source file'), 'fixture');

        return true;
    }

    private function sameFile(string $left, string $right): bool
    {
        $leftReal = realpath($left);
        $rightReal = realpath($right);

        return (false === $leftReal ? $left : $leftReal) === (false === $rightReal ? $right : $rightReal);
    }
}
