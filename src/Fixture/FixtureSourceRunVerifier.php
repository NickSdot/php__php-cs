<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use InternalsCS\PhpSrcTestStyle\PhptFile;
use InternalsCS\Support\FileSystem;

use function bin2hex;
use function dirname;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;

final readonly class FixtureSourceRunVerifier implements FixtureSourceVerifier
{
    private const array NON_ISOLATED_SECTIONS = [
        'SKIPIF',
        'FILE_EXTERNAL',
        'REDIRECTTEST',
        'EXPECT_EXTERNAL',
        'EXPECTF_EXTERNAL',
        'EXPECTREGEX_EXTERNAL',
    ];

    public function __construct(
        private FileSystem $files = new FileSystem(),
    ) {}

    public function canSelect(
        FixtureSource $source,
        FixtureSourceVerification $verification,
    ): bool {
        if (!$this->isSelfContained($source)) {
            return false;
        }

        if (!$verification->runner instanceof FixtureOriginalRunner) {
            return true;
        }

        $result = $this->runIsolated(
            $source,
            $verification->runner->runOriginalFile(...),
        );

        return $result['passed'];
    }

    public function canRewrite(
        FixtureSource $source,
        FixtureSourceVerification $verification,
    ): bool {
        $result = $this->runIsolated(
            $source,
            $verification->runner->printFile(...),
        );

        return $result['changed'] && !$result['failed'];
    }

    private function isSelfContained(FixtureSource $source): bool
    {
        $file = new PhptFile($source->sourcePath, dirname($source->sourcePath));

        foreach (self::NON_ISOLATED_SECTIONS as $section) {
            if ($file->hasSection($section)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @template T
     *
     * @param callable(string): T $run
     * @return T
     */
    private function runIsolated(FixtureSource $source, callable $run): mixed
    {
        $directory = sys_get_temp_dir() . '/internals-cs-fixture-source-' . bin2hex(random_bytes(12));
        $path = $directory . '/old.phpt';

        $this->files->write($path, $this->files->read($source->sourcePath, 'source file'), 'isolated source fixture');

        try {
            return $run($path);
        } finally {
            $this->files->deleteFileIfExists($path, 'isolated source fixture');

            if (!rmdir($directory)) {
                throw new \RuntimeException('Cannot delete isolated source fixture directory: ' . $directory);
            }
        }
    }
}
