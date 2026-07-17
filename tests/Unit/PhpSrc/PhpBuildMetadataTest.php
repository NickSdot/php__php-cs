<?php

declare(strict_types=1);

namespace Tests\Unit\PhpSrc;

use InternalsCS\PhpSrc\PhpBuildMetadata;
use PHPUnit\Framework\TestCase;

final class PhpBuildMetadataTest extends TestCase
{
    public function testCheckoutMatchUsesHeadAndProfileOnly(): void
    {
        $built = new PhpBuildMetadata(
            phpSrcDir: '/old-runtime-path/source',
            head: 'abc123',
            statusHash: 'clean',
            profileSignature: 'profile',
        );
        $current = new PhpBuildMetadata(
            phpSrcDir: '/new-runtime-path/source',
            head: 'abc123',
            statusHash: 'dirty',
            profileSignature: 'profile',
        );

        self::assertFalse($built->matches($current));
        self::assertTrue($built->matchesCheckout($current));
    }

    public function testCheckoutMatchRejectsDifferentHead(): void
    {
        $built = new PhpBuildMetadata(
            phpSrcDir: '/php-src',
            head: 'abc123',
            statusHash: 'clean',
            profileSignature: 'profile',
        );
        $current = new PhpBuildMetadata(
            phpSrcDir: '/php-src',
            head: 'def456',
            statusHash: 'clean',
            profileSignature: 'profile',
        );

        self::assertFalse($built->matchesCheckout($current));
    }
}
