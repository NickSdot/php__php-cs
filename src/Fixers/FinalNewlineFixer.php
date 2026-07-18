<?php

declare(strict_types=1);

namespace InternalsCS\Fixers;

use InternalsCS\FinalNewline;
use InternalsCS\PhpSrcTestStyle\PhptFixer;

use function mb_substr_count;

final class FinalNewlineFixer extends PhptFixer
{
    private ?string $contents = null;

    public function __construct(
        private readonly FinalNewline $finalNewline = new FinalNewline(),
    ) {}

    public function name(): string
    {
        return 'final-newline';
    }

    protected function planPhptRewrite(): bool
    {
        $this->resetDiagnostics();
        $this->contents = null;

        $current = $this->phptFile()->contents();

        if ($this->finalNewline->isNormalized($current)) {
            return false;
        }

        $this->contents = $this->finalNewline->normalize($current);
        $this->markLine(mb_substr_count($current, "\n", '8bit') + 1);

        return true;
    }

    protected function apply(): void
    {
        if (null === $this->contents) {
            throw new \LogicException('Final-newline fixer was applied before collection completed');
        }

        $this->phptFile()->replaceContents($this->contents);
    }

    protected function hasPlannedRewrite(): bool
    {
        return null !== $this->contents;
    }
}
