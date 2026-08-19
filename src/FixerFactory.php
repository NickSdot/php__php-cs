<?php

declare(strict_types=1);

namespace InternalsCS;

use InternalsCS\Fixers\ExceptionOutput\ExceptionOutputFixer;

final readonly class FixerFactory
{
    /** @param class-string<Fixer> $fixerClass */
    public function create(string $fixerClass, FixerRunOptions $options): Fixer
    {
        if (ExceptionOutputFixer::class === $fixerClass) {
            return new ExceptionOutputFixer(skipNormalizationOnly: $options->skipNormalizationOnly);
        }

        return new $fixerClass();
    }
}
