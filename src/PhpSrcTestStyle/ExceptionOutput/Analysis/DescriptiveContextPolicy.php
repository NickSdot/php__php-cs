<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis;

use function in_array;
use function mb_trim;
use function str_replace;

final readonly class DescriptiveContextPolicy
{
    public function __construct(
        private TrashLiteralPolicy $trash = new TrashLiteralPolicy(),
        private MarkerPrefixPolicy $markers = new MarkerPrefixPolicy(),
    ) {}

    public function isDescriptiveLiteral(string $literal): bool
    {
        if ($this->trash->isTrash($literal)) {
            return false;
        }

        if ($this->markers->isMarkerLiteral($literal)) {
            return false;
        }

        if ($this->isStructuralLiteral($literal)) {
            return false;
        }

        return '' !== mb_trim($literal);
    }

    private function isStructuralLiteral(string $literal): bool
    {
        if (in_array($literal, [': ', ' : ', ', ', ' in ', ' on line ', '.', ' failed', '()', '[', '] ', '<br>', '<br />'], true)) {
            return true;
        }

        $normalized = mb_trim(str_replace(["\r", "\n", "\t"], ' ', $literal));

        return in_array($normalized, ['.', 'failed', '()', '()"', '"', '[', ']', '<br>', '<br />'], true);
    }
}
