<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Analysis;

use function array_unique;
use function array_values;
use function in_array;
use function mb_strlen;
use function mb_strtolower;
use function mb_substr;
use function mb_trim;
use function preg_match;
use function preg_replace;
use function str_ends_with;
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
        'err',
        'error',
        'error found',
        'exception',
        'exception caught',
        'exception thrown',
        'expected exception',
        'in catch',
        'logicexception',
        'ok',
        'parse error',
        'pdoexception message',
        'runtimeexception thrown',
        'safely caught',
        'test',
        'valueerror',
    ];

    public function isTrash(string $literal): bool
    {
        return null !== $this->label($literal);
    }

    /** @return list<string> */
    public function withoutLeadingTrashCandidates(string $line): array
    {
        $candidates = [];
        $length = mb_strlen($line, '8bit');

        for ($offset = 1; $offset < $length; $offset++) {
            $prefix = mb_substr($line, 0, $offset, '8bit');

            if (!$this->isTrash($prefix)) {
                continue;
            }

            $candidate = mb_substr($line, $offset, null, '8bit');

            if ('' === $candidate) {
                continue;
            }

            $candidates[] = $candidate;
        }

        for ($start = 1; $start < $length; $start++) {
            $context = mb_substr($line, 0, $start, '8bit');

            if (!str_ends_with($context, ':')) {
                continue;
            }

            for ($end = $start + 1; $end < $length; $end++) {
                $trash = mb_substr($line, $start, $end - $start, '8bit');

                if (!$this->isTrash($trash)) {
                    continue;
                }

                $candidate = $context . mb_substr($line, $end, null, '8bit');

                if ($candidate !== $line) {
                    $candidates[] = $candidate;
                }
            }
        }

        return array_values(array_unique($candidates));
    }

    public function label(string $literal): ?string
    {
        $label = $this->normalize($literal);

        if ('' === $label) {
            return '' === $literal ? 'empty' : null;
        }

        if (in_array($label, self::LABELS, true)) {
            return $label;
        }

        if (1 === preg_match('/^(?:[a-z_\\\\][a-z0-9_\\\\]*(?:exception|error)|soapfault)(?: thrown)?$/', $label)) {
            return 'exception type';
        }

        return null;
    }

    private function normalize(string $literal): string
    {
        $label = str_replace(["\r", "\n", "\t"], ' ', $literal);
        $label = mb_trim($label);
        $label = mb_trim($label, " *:-[]().\"'!");
        $label = preg_replace('/\s+/', ' ', $label);

        return mb_strtolower((string) $label);
    }
}
