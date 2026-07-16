<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis;

use function in_array;
use function mb_strtolower;
use function mb_trim;
use function preg_replace;
use function str_replace;

final readonly class TrashLiteralPolicy
{
    /** @var list<string> */
    private const array LABELS = [
        'assert',
        'assert()',
        'assertion failure',
        'caught',
        'caught exception',
        'caught exception with message',
        'caught fatalexception',
        'caught in',
        'error',
        'error found',
        'exception',
        'exception caught',
        'exception thrown for invalid flags',
        'expected exception',
        'expected exception for class-based reflection',
        'logicexception',
        'ok',
        'parse error',
        'runtimeexception thrown',
        'test',
        'unexpected exception',
        'valueerror',
    ];

    public function isTrash(string $literal): bool
    {
        return null !== $this->label($literal);
    }

    public function label(string $literal): ?string
    {
        $label = $this->normalize($literal);

        if ('' === $label) {
            return 'empty';
        }

        if (in_array($label, self::LABELS, true)) {
            return $label;
        }

        return null;
    }

    private function normalize(string $literal): string
    {
        $label = str_replace(["\r", "\n", "\t"], ' ', $literal);
        $label = mb_trim($label);
        $label = mb_trim($label, " *:-[]().\"'");
        $label = preg_replace('/\s+/', ' ', $label);

        return mb_strtolower((string) $label);
    }
}
