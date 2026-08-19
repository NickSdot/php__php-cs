<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Fixing;

use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputPart;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputParts;

use function count;
use function mb_trim;

final readonly class QuotedClassMessageOutput
{
    public function __construct(
        private OutputPartMatcher $parts = new OutputPartMatcher(),
    ) {}

    public function wrapper(OutputParts $output, string $catchVariable): ?string
    {
        $parts = $output->parts;

        if (count($parts) < 4 || !$this->parts->isExceptionClass($parts[0], $catchVariable)) {
            return null;
        }

        $wrapper = match ($parts[1]->value) {
            ': \'' => '\'',
            ': "' => '"',
            default => null,
        };

        if (null === $wrapper || !$this->parts->isExceptionMessage($parts[2], $catchVariable)) {
            return null;
        }

        for ($i = 3; $i < count($parts); $i++) {
            if (!$this->isClosingWrapperOrNewline($parts[$i], $wrapper)) {
                return null;
            }
        }

        return $wrapper;
    }

    private function isClosingWrapperOrNewline(OutputPart $part, string $wrapper): bool
    {
        if ($this->parts->isNewline($part)) {
            return true;
        }

        return $this->parts->isLiteral($part) && $wrapper === mb_trim($part->value);
    }
}
