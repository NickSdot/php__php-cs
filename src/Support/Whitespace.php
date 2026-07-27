<?php

declare(strict_types=1);

namespace InternalsCS\Support;

use function str_replace;

final readonly class Whitespace
{
    public static function normalizeLineEndingsToLf(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }

    public static function lineBreaksAndTabsToSpaces(string $value): string
    {
        return str_replace(["\r", "\n", "\t"], ' ', $value);
    }
}
