<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing;

use InternalsCS\PhpSrcTestStyle\VerifiedPhptFixer;

use function mb_substr;
use function str_contains;
use function usort;

final class CanonicalFixer extends VerifiedPhptFixer
{
    private ?string $codeSection = null;

    private ?string $newCode = null;

    public function __construct(
        private readonly CanonicalPlanner $planner = new CanonicalPlanner(),
        private readonly CanonicalUpdater $expectedOutput = new CanonicalUpdater(),
    ) {}

    public function name(): string
    {
        return 'canonical-exception-output';
    }

    protected function planPhptRewrite(): bool
    {
        $this->resetDiagnostics();
        $this->codeSection = null;
        $this->newCode = null;

        $file = $this->phptFile();
        $this->codeSection = $file->codeSectionName();

        if (null === $this->codeSection || $file->hasSection('XFAIL')) {
            return false;
        }

        $code = $file->getSection($this->codeSection);

        if (null === $code || !str_contains($code, 'getMessage')) {
            return false;
        }

        $plans = $this->planner->plans($code);

        if ([] === $plans) {
            return false;
        }

        usort($plans, fn($a, $b): int => $b->startOffset <=> $a->startOffset);
        $changed = false;

        foreach ($plans as $plan) {
            $current = mb_substr($code, $plan->startOffset, $plan->endOffset - $plan->startOffset, '8bit');

            if ($current === $plan->replacement) {
                continue;
            }

            $code = mb_substr($code, 0, $plan->startOffset, '8bit')
                . $plan->replacement
                . mb_substr($code, $plan->endOffset, null, '8bit');

            $this->markLineForOffset($code, $plan->startOffset);
            $changed = true;
        }

        if (!$changed) {
            return false;
        }

        $this->newCode = $code;

        return true;
    }

    protected function hasPlannedRewrite(): bool
    {
        return null !== $this->codeSection && null !== $this->newCode;
    }

    protected function apply(): void
    {
        if (null === $this->codeSection || null === $this->newCode) {
            throw new \LogicException('Canonical fixer was applied before collection completed');
        }

        $this->phptFile()->setSection($this->codeSection, $this->newCode);
    }

    #[\Override]
    protected function changesOutput(): bool
    {
        return true;
    }

    #[\Override]
    protected function updateExpectedOutput(string $section, string $expected, string $actual): ?string
    {
        $update = $this->expectedOutput->update($section, $expected, $actual);

        if (null !== $update->output) {
            return $update->output;
        }

        if (null !== $update->failure) {
            $this->setExpectedUpdateFailure($update->failure);
        }

        return null;
    }
}
