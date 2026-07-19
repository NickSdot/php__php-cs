<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use InternalsCS\Support\MarkdownTable;
use PHPUnit\Framework\TestCase;

final class MarkdownTableTest extends TestCase
{
    public function testRendersAlignedRowsAndEscapesPipes(): void
    {
        self::assertSame(
            [
                '',
                '| Name  | Value  |',
                '|-------|--------|',
                '| short | 1      |',
                '| a\\|b  | longer |',
                '',
            ],
            new MarkdownTable()->render(
                ['Name', 'Value'],
                [
                    ['short', 1],
                    ['a|b', 'longer'],
                ],
            ),
        );
    }
}
