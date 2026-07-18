<?php

declare(strict_types=1);

namespace InternalsCS;

use function preg_replace;
use function str_contains;
use function str_ends_with;

final readonly class FinalNewline
{
    public function normalize(string $contents): string
    {
        $withoutTrailingLineEndings = preg_replace('/(?:\r\n|\n|\r)+\z/', '', $contents);

        if (null === $withoutTrailingLineEndings) {
            throw new \RuntimeException('Cannot normalise final newline');
        }

        return $withoutTrailingLineEndings . $this->lineEnding($contents);
    }

    public function isNormalized(string $contents): bool
    {
        return $contents === $this->normalize($contents);
    }

    private function lineEnding(string $contents): string
    {
        if (str_ends_with($contents, "\r\n") || str_contains($contents, "\r\n")) {
            return "\r\n";
        }

        return "\n";
    }
}
