<?php

declare(strict_types=1);

namespace InternalsCS;

use InternalsCS\Fixers\ExceptionOutput\ExceptionOutputFixer;
use InternalsCS\Fixers\FinalNewline\FinalNewlineFixer;

use function array_keys;
use function array_values;
use function implode;

final readonly class FixerRegistry
{
    /** @var array<string, class-string<Fixer>> */
    private const array FIXERS = [
        'exception-output' => ExceptionOutputFixer::class,
        'final-newline' => FinalNewlineFixer::class,
    ];

    /** @return list<class-string<Fixer>> */
    public function all(): array
    {
        return array_values(self::FIXERS);
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
                'Unknown fixer: ' . $name . '. Known fixers: ' . $this->knownFixersLine(),
            );
        }

        return $classes;
    }

    public function knownFixersLine(): string
    {
        return implode(', ', array_keys(self::FIXERS));
    }
}
