<?php

declare(strict_types=1);

namespace InternalsCS;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalFixer;
use InternalsCS\PhpSrcTestStyle\FinalNewlineFixer;

use function array_keys;
use function implode;

final readonly class FixerRegistry
{
    /** @var array<string, class-string<Fixer>> */
    private const array FIXERS = [
        'canonical-exception-output' => CanonicalFixer::class,
        'final-newline' => FinalNewlineFixer::class,
    ];

    /** @return list<class-string<Fixer>> */
    public function all(): array
    {
        return [
            CanonicalFixer::class,
            FinalNewlineFixer::class,
        ];
    }

    /**
     * @param list<string> $names
     * @return list<class-string<Fixer>>
     */
    public function selected(array $names): array
    {
        if ([] === $names) {
            return $this->all();
        }

        $classes = [];

        foreach ($names as $name) {
            $classes[] = self::FIXERS[$name] ?? throw new \InvalidArgumentException(
                'Unknown fixer: ' . $name . '. Known fixers: ' . $this->knownFixers(),
            );
        }

        return $classes;
    }

    public function knownFixers(): string
    {
        return implode(', ', array_keys(self::FIXERS));
    }
}
