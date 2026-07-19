<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Fixing\Rules;

use function array_unique;
use function array_values;
use function basename;
use function in_array;
use function mb_ltrim;
use function mb_strtolower;
use function mb_trim;
use function str_replace;

final readonly class CatchTypeLabels
{
    /** @param list<string> $catchTypes */
    public function contains(array $catchTypes, string $label): bool
    {
        return in_array($this->normalize($label), $this->labels($catchTypes), true);
    }

    /**
     * @param list<string> $catchTypes
     * @return list<string>
     */
    public function names(array $catchTypes): array
    {
        $names = [];

        foreach ($catchTypes as $type) {
            $type = mb_ltrim($type, '\\');
            $names[] = $type;
            $names[] = basename(str_replace('\\', '/', $type));
        }

        return array_values(array_unique($names));
    }

    private function normalize(string $label): string
    {
        $label = mb_trim($label);
        $label = mb_trim($label, ': ');
        $label = mb_ltrim($label, '\\');

        return mb_strtolower($label);
    }

    /**
     * @param list<string> $catchTypes
     * @return list<string>
     */
    private function labels(array $catchTypes): array
    {
        $labels = [];

        foreach ($this->names($catchTypes) as $type) {
            $labels[] = mb_strtolower($type);
        }

        return array_values(array_unique($labels));
    }
}
