<?php

declare(strict_types=1);

namespace Tests\Unit\PhpSrc;

use InternalsCS\PhpSrc\PkgConfigPathResolver;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;
use function touch;

final class PkgConfigPathResolverTest extends TestCase
{
    public function testKeepsExistingPkgConfigPathFirst(): void
    {
        $resolver = new PkgConfigPathResolver();

        self::assertSame(
            ['/first', '/second'],
            $resolver->resolve(['PKG_CONFIG_PATH' => '/first' . \PATH_SEPARATOR . '/second'], []),
        );
    }

    public function testFindsPackagesInVersionedPackageManagerDirectories(): void
    {
        $root = $this->tempDir();
        $icu = $root . '/Cellar/icu4c@78/78.3/lib/pkgconfig';
        $ldap = $root . '/opt/openldap/lib/pkgconfig';
        $sqlite = $root . '/lib/x86_64-linux-gnu/pkgconfig';
        mkdir($icu, recursive: true);
        mkdir($ldap, recursive: true);
        mkdir($sqlite, recursive: true);
        touch($icu . '/demo-uc.pc');
        touch($ldap . '/demo-ldap.pc');
        touch($sqlite . '/demo-sqlite.pc');

        $resolver = new PkgConfigPathResolver([$root]);

        self::assertSame(
            [$icu, $ldap, $sqlite],
            $resolver->resolve([], ['demo-uc', 'demo-ldap', 'demo-sqlite']),
        );
    }

    private function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/pkg-config-paths-' . bin2hex(random_bytes(6));
        mkdir($dir);

        return $dir;
    }
}
