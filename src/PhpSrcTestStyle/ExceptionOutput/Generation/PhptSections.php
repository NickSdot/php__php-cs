<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Generation;

use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function mb_strlen;
use function mb_substr;
use function mb_substr_count;
use function preg_match_all;

final readonly class PhptSections
{
    public function code(string $contents): ?PhptSection
    {
        return $this->first($contents, ['FILE', 'FILEEOF']);
    }

    /** @param list<string> $names */
    private function first(string $contents, array $names): ?PhptSection
    {
        $matched = preg_match_all('/^--([_A-Z]+)--[ \t]*(?:\r\n|\n|\r|$)/m', $contents, $matches, PREG_OFFSET_CAPTURE);

        if (false === $matched || 0 === $matched) {
            return null;
        }

        $count = count($matches[0]);

        for ($i = 0; $i < $count; $i++) {
            $name = $this->matchText($matches[1][$i]);

            if (null === $name || !in_array($name, $names, true)) {
                continue;
            }

            $header = $this->matchText($matches[0][$i]);
            $start = $this->matchOffset($matches[0][$i]);
            $nextStart = $i + 1 < $count ? $this->matchOffset($matches[0][$i + 1]) : null;

            if (null === $header || null === $start) {
                continue;
            }

            $contentStart = $start + mb_strlen($header, '8bit');
            $contentEnd = $nextStart ?? mb_strlen($contents, '8bit');

            return new PhptSection(
                contents: mb_substr($contents, $contentStart, $contentEnd - $contentStart, '8bit'),
                startLine: mb_substr_count(mb_substr($contents, 0, $contentStart, '8bit'), "\n", '8bit') + 1,
            );
        }

        return null;
    }

    private function matchText(mixed $match): ?string
    {
        if (!is_array($match)) {
            return null;
        }

        $text = $match[0] ?? null;

        return is_string($text) ? $text : null;
    }

    private function matchOffset(mixed $match): ?int
    {
        if (!is_array($match)) {
            return null;
        }

        $offset = $match[1] ?? null;

        return is_int($offset) ? $offset : null;
    }
}
