<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis;

use function mb_substr;
use function sha1;

final readonly class Fingerprint
{
    public string $id;

    public string $shortHash;

    public function __construct(
        public OutputFamily $family,
        public string $payload,
    ) {
        $this->id = $family->value . ':' . sha1($payload);
        $this->shortHash = mb_substr(sha1($this->id), 0, 10);
    }
}
