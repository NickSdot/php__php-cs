<?php

declare(strict_types=1);

namespace InternalsCS\Support;

use function getenv;
use function is_array;
use function is_string;

final readonly class ProcessEnvironment
{
    /** @return array<string, string> */
    public function variables(): array
    {
        $environment = getenv();

        if (!is_array($environment)) {
            $environment = [];
        }

        foreach ($_ENV as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            $environment[$key] = $value;
        }

        return $environment;
    }
}
