<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis;

use function in_array;
use function mb_trim;

final readonly class DescriptiveContextPolicy
{
    public function __construct(
        private TrashLiteralPolicy $trash = new TrashLiteralPolicy(),
    ) {}

    public function isDescriptiveLiteral(string $literal): bool
    {
        if ($this->trash->isTrash($literal)) {
            return false;
        }

        if ($this->isStructuralLiteral($literal)) {
            return false;
        }

        return '' !== mb_trim($literal);
    }

    private function isStructuralLiteral(string $literal): bool
    {
        return in_array($literal, [': ', ' in ', ' on line ', '.', ' failed', '()'], true);
    }
}
