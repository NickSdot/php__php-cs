<?php

declare(strict_types=1);

namespace Tests\Unit;

use InternalsCS\Fixers\FinalNewline\FinalNewline;
use PHPUnit\Framework\TestCase;

final class FinalNewlineTest extends TestCase
{
    public function testAddsMissingFinalNewline(): void
    {
        self::assertSame("line\n", new FinalNewline()->normalize('line'));
    }

    public function testKeepsSingleFinalNewline(): void
    {
        self::assertSame("line\n", new FinalNewline()->normalize("line\n"));
    }

    public function testCollapsesExtraFinalNewlines(): void
    {
        self::assertSame("line\n", new FinalNewline()->normalize("line\n\n"));
    }

    public function testPreservesCrLfLineEndings(): void
    {
        self::assertSame("line\r\n", new FinalNewline()->normalize("line\r\n\r\n"));
    }
}
