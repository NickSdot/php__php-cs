<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\FinalNewline\Generation;

use InternalsCS\Fixture\FixtureRewriteRunner;
use InternalsCS\Fixture\FixtureSource;
use InternalsCS\Fixture\FixtureSourceReducer;

use function count;

final readonly class MinimalFixtureSourceReducer implements FixtureSourceReducer
{
    public function reduce(FixtureSource $source, FixtureRewriteRunner $runner): ?string
    {
        $keys = $source->fixtureCaseKeys();

        if (1 !== count($keys)) {
            return null;
        }

        return match ($keys[0]) {
            'missing-final-newline' => "--TEST--\nFinal newline: missing\n--FILE--\n<?php\n--EXPECT--",
            'extra-final-newlines' => "--TEST--\nFinal newline: extra\n--FILE--\n<?php\n--EXPECT--\n\n",
            default => null,
        };
    }
}
