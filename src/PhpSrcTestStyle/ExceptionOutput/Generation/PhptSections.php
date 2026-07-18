<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Generation;

use function count;
use function in_array;
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

    public function expected(string $contents): ?PhptSection
    {
        return $this->first($contents, ['EXPECT', 'EXPECTF', 'EXPECTREGEX']);
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
            $name = $matches[1][$i][0];

            if (!in_array($name, $names, true)) {
                continue;
            }

            $contentStart = $matches[0][$i][1] + mb_strlen($matches[0][$i][0], '8bit');
            $contentEnd = $i + 1 < $count ? $matches[0][$i + 1][1] : mb_strlen($contents, '8bit');

            return new PhptSection(
                name: $name,
                contents: mb_substr($contents, $contentStart, $contentEnd - $contentStart, '8bit'),
                startLine: mb_substr_count(mb_substr($contents, 0, $contentStart, '8bit'), "\n", '8bit') + 1,
            );
        }

        return null;
    }
}
