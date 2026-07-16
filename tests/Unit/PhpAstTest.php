<?php

declare(strict_types=1);

namespace Tests\Unit;

use InternalsCS\PhpAst;
use PHPUnit\Framework\TestCase;

final class PhpAstTest extends TestCase
{
    public function testParsesCodeFragmentsWithOffsetDelta(): void
    {
        $parsed = new PhpAst()->parse('echo "ok";');

        self::assertNotNull($parsed);
        self::assertSame(6, $parsed->offsetDelta);
        self::assertCount(1, $parsed->statements);
    }

    public function testFindsCatchBlocksThroughChildStatements(): void
    {
        $parsed = new PhpAst()->parse(<<<'PHP'
            <?php
            class Example {
                public function run(): void {
                    try {
                        throw new RuntimeException('broken');
                    } catch (Throwable $e) {
                    }
                }
            }
            PHP);

        self::assertNotNull($parsed);
        self::assertCount(1, new PhpAst()->catchBlocks($parsed->statements));
    }
}
