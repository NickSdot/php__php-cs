<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

use function count;
use function hash;
use function implode;
use function sort;

final readonly class FixtureCaseName
{
    public function fromCandidate(FixtureCandidate $candidate): string
    {
        return $this->fromFixtureCaseKeys([$candidate->fixtureCaseKey]);
    }

    public function fromFixtureSource(FixtureSource $source): string
    {
        return $this->fromFixtureCaseKeys($source->fixtureCaseKeys());
    }

    /** @param list<string> $fixtureCaseKeys */
    private function fromFixtureCaseKeys(array $fixtureCaseKeys): string
    {
        if ([] === $fixtureCaseKeys) {
            throw new \LogicException('Cannot name a fixture source without fixture case keys');
        }

        sort($fixtureCaseKeys);

        $prefix = 1 === count($fixtureCaseKeys) ? 'flavour' : 'flavours';

        return $prefix . '_' . hash('sha1', implode("\n", $fixtureCaseKeys));
    }
}
