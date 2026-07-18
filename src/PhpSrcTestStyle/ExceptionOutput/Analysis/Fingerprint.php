<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis;

use function sha1;

final readonly class Fingerprint
{
    public string $id;

    public function __construct(
        OutputFamily $family,
        string $payload,
    ) {
        $this->id = $family->value . ':' . sha1($payload);
    }
}
