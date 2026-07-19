<?php

declare(strict_types=1);

namespace Tests\Unit;

use InternalsCS\FixRunEntry;
use InternalsCS\FixRunReportWriter;
use InternalsCS\FixRunResult;
use InternalsCS\FixRunStatus;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_get_contents;
use function is_file;
use function mb_strpos;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

final class FixRunReportWriterTest extends TestCase
{
    public function testWritesTimestampedReportWithSkipsBeforeFixes(): void
    {
        $dir = $this->makeTempDir();
        $result = new FixRunResult(scannedFiles: 12, check: false);
        $result->add(new FixRunEntry(
            status: FixRunStatus::Skipped,
            file: 'ext/phar/tests/032.phpt',
            fixer: 'exception-output',
            location: 'FILE line 10',
            reason: 'EXPECTF update was not provable',
        ));
        $result->add(new FixRunEntry(
            status: FixRunStatus::Fixed,
            file: 'ext/intl/tests/uconverter_bug66873.phpt',
            fixer: 'exception-output',
            location: 'FILE line 7',
        ));

        $path = new FixRunReportWriter()->write(
            reportDir: $dir,
            timestamp: new \DateTimeImmutable('2026-07-19T12:34:56+00:00'),
            phpSrcDir: '/php-src',
            targets: ['ext/phar'],
            fixers: ['exception-output'],
            result: $result,
        );

        self::assertSame($dir . '/20260719-123456-000000-phar.md', $path);
        self::assertTrue(is_file($path));

        $contents = (string) file_get_contents($path);
        $skipsPosition = mb_strpos($contents, '## Skips');
        $fixesPosition = mb_strpos($contents, '## Fixes');

        self::assertStringContainsString('| Scanned files', $contents);
        self::assertStringContainsString('| Fixed', $contents);
        self::assertStringContainsString('| Skipped', $contents);
        self::assertStringContainsString('| ext/phar/tests/032.phpt | exception-output | FILE line 10 | EXPECTF update was not provable |', $contents);
        self::assertStringContainsString('| ext/intl/tests/uconverter_bug66873.phpt | exception-output | FILE line 7 |', $contents);
        self::assertStringContainsString('EXPECTF update was not provable', $contents);
        self::assertIsInt($skipsPosition);
        self::assertIsInt($fixesPosition);
        self::assertLessThan($fixesPosition, $skipsPosition);
        self::assertStringEndsWith("## Fixes\n\n| File                                    | Fixer            | Location    |\n|-----------------------------------------|------------------|-------------|\n| ext/intl/tests/uconverter_bug66873.phpt | exception-output | FILE line 7 |\n\n", $contents);
    }

    private function makeTempDir(): string
    {
        $root = sys_get_temp_dir() . '/fix-run-report-' . bin2hex(random_bytes(6));
        mkdir($root);

        return $root;
    }
}
