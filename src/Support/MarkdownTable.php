<?php

declare(strict_types=1);

namespace InternalsCS\Support;

use function array_values;
use function implode;
use function max;
use function mb_str_pad;
use function mb_strlen;
use function str_repeat;
use function str_replace;

final readonly class MarkdownTable
{
    /**
     * @param list<string> $headers
     * @param list<list<int|string>> $rows
     * @return list<string>
     */
    public function render(array $headers, array $rows): array
    {
        $widths = $this->columnWidths($headers, $rows);
        $lines = [
            '',
            $this->row($headers, $widths),
            $this->separator($widths),
        ];

        foreach ($rows as $row) {
            $lines[] = $this->row($row, $widths);
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @param list<string> $headers
     * @param list<list<int|string>> $rows
     * @return list<int>
     */
    private function columnWidths(array $headers, array $rows): array
    {
        $widths = [];

        foreach ($headers as $header) {
            $widths[] = mb_strlen($this->cell($header));
        }

        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                $widths[$index] = max($widths[$index] ?? 0, mb_strlen($this->cell((string) $cell)));
            }
        }

        return array_values($widths);
    }

    /**
     * @param list<int|string> $row
     * @param list<int> $widths
     */
    private function row(array $row, array $widths): string
    {
        $cells = [];

        foreach ($row as $index => $cell) {
            $cells[] = mb_str_pad($this->cell((string) $cell), $widths[$index]);
        }

        return '| ' . implode(' | ', $cells) . ' |';
    }

    /** @param list<int> $widths */
    private function separator(array $widths): string
    {
        $cells = [];

        foreach ($widths as $width) {
            $cells[] = str_repeat('-', $width + 2);
        }

        return '|' . implode('|', $cells) . '|';
    }

    private function cell(string $value): string
    {
        return str_replace('|', '\|', $value);
    }
}
