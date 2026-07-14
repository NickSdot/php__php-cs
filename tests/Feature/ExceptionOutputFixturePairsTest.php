<?php

declare(strict_types=1);

namespace InternalsCS\Tests;

use InternalsCS\Fixtures\PhptFixtureFiles;
use InternalsCS\Support\UnifiedDiff;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExceptionOutputFixturePairsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function fixtureDirs(): iterable
    {
        $root = dirname(__DIR__) . '/Fixtures/exception_output_styles';

        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (!new PhptFixtureFiles($dir)->containsFixtureFiles()) {
                continue;
            }

            yield basename($dir) => [$dir];
        }
    }

    #[DataProvider('fixtureDirs')]
    public function testFixturePairShapeIsConsistent(string $fixtureDir): void
    {
        $old = $fixtureDir . '/old.phpt';
        $new = $fixtureDir . '/new.phpt';
        $diff = $fixtureDir . '/ran.diff';

        self::assertFileExists($old);
        self::assertSame(is_file($new), is_file($diff), basename($fixtureDir));

        if (!is_file($new)) {
            return;
        }

        self::assertSame(
            new UnifiedDiff()->betweenFiles($old, $new, 'old.phpt', 'new.phpt'),
            file_get_contents($diff),
            basename($fixtureDir),
        );
    }
}
