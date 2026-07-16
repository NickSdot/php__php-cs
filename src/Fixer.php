<?php

declare(strict_types=1);

namespace InternalsCS;

interface Fixer
{
    public function name(): string;

    public function supports(SourceFile $file): bool;

    public function collect(SourceFile $file): bool;

    public function persist(): bool;

    public function location(): string;

    public function failureReason(): string;

    public function cleanup(): void;
}
