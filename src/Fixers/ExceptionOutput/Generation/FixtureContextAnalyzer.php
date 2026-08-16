<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Generation;

use InternalsCS\Fixers\ExceptionOutput\Analysis\Window;
use InternalsCS\Fixers\ExceptionOutput\Fixing\LeadingSeparatorOutput;
use InternalsCS\Fixers\ExceptionOutput\Fixing\OutputPartMatcher;
use InternalsCS\Fixers\ExceptionOutput\Fixing\OutputStatementPresence;
use InternalsCS\Fixers\ExceptionOutput\Fixing\PreviousOutputNewlinePlanner;
use InternalsCS\Fixers\ExceptionOutput\Fixing\StatementFactory;
use InternalsCS\PhpAst;
use InternalsCS\TextEdit;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

use function array_any;
use function array_values;
use function count;
use function is_string;

final readonly class FixtureContextAnalyzer
{
    public function __construct(
        private PhpAst $ast = new PhpAst(),
        private StatementFactory $statements = new StatementFactory(),
        private LeadingSeparatorOutput $leadingSeparator = new LeadingSeparatorOutput(),
        private OutputPartMatcher $parts = new OutputPartMatcher(),
        private OutputStatementPresence $outputPresence = new OutputStatementPresence(),
        private PreviousOutputNewlinePlanner $previousNewline = new PreviousOutputNewlinePlanner(),
    ) {}

    /** @param list<TextEdit> $plans */
    public function analyze(string $code, Window $window, array $plans): FixtureContext
    {
        $parsed = $this->ast->parse($code);

        if (null === $parsed) {
            return new FixtureContext();
        }

        $source = new FixtureContextSource($code, $parsed->offsetDelta, $window, $plans);

        return $this->inStatementLists($parsed->statements, $source)
            ?? new FixtureContext();
    }

    /** @param list<Stmt> $statements */
    private function inStatementLists(array $statements, FixtureContextSource $source): ?FixtureContext
    {
        foreach ($statements as $offset => $statement) {

            $context = $statement instanceof Stmt\TryCatch
                ? $this->inTryCatch(
                    try: $statement,
                    previous: $statements[$offset - 1] ?? null,
                    following: $statements[$offset + 1] ?? null,
                    source: $source,
                )
                : $this->inChildStatementLists($statement, $source);

            if (null !== $context) {
                return $context;
            }
        }

        return null;
    }

    private function inTryCatch(Stmt\TryCatch $try, ?Stmt $previous, ?Stmt $following, FixtureContextSource $source): ?FixtureContext
    {
        foreach ($try->catches as $catch) {

            $context = $this->forCatch(
                catch: $catch,
                try: $try,
                previous: $previous,
                following: $following,
                source: $source,
            );

            if (null !== $context) {
                return $context;
            }
        }

        $context = $this->inStatementLists(array_values($try->stmts), $source);

        if (null !== $context) {
            return $context;
        }

        foreach ($try->catches as $catch) {

            $context = $this->inStatementLists(array_values($catch->stmts), $source);

            if (null !== $context) {
                return $context;
            }
        }

        return null === $try->finally
            ? null
            : $this->inStatementLists(array_values($try->finally->stmts), $source);
    }

    private function inChildStatementLists(Stmt $statement, FixtureContextSource $source): ?FixtureContext
    {
        foreach ($this->ast->childStatementLists($statement) as $children) {

            $context = $this->inStatementLists($children, $source);

            if (null !== $context) {
                return $context;
            }
        }

        return null;
    }

    private function forCatch(Stmt\Catch_ $catch, Stmt\TryCatch $try, ?Stmt $previous, ?Stmt $following, FixtureContextSource $source): ?FixtureContext
    {
        if (!$catch->var instanceof Expr\Variable || !is_string($catch->var->name)) {
            return null;
        }

        $catchStatements = array_values($catch->stmts);
        $range = $this->directStatementRange($catchStatements, $source);

        if (null === $range) {
            return null;
        }

        $followingNewlineMoved = count($catchStatements) - 1 === $range['end']
            && null !== $following
            && $this->hasPlanFor($following, $source);

        $followingTraceOutput = $this->hasFollowingTraceOutput(
            following: $catchStatements[$range['end'] + 1] ?? null,
            catchVariable: $catch->var->name,
            source: $source,
        );

        return new FixtureContext(
            previousSeparator: 0 === $range['start']
                ? $this->previousSeparatorContext(
                    first: $catchStatements[0],
                    try: $try,
                    previous: $previous,
                    catchVariable: $catch->var->name,
                    source: $source,
                )
                : PreviousSeparatorContext::None,
            followingNewlineMoved: $followingNewlineMoved,
            followingTraceOutput: $followingTraceOutput,
        );
    }

    private function previousSeparatorContext(Stmt $first, Stmt\TryCatch $try, ?Stmt $previous, string $catchVariable, FixtureContextSource $source): PreviousSeparatorContext
    {
        if (null === $previous) {
            return PreviousSeparatorContext::None;
        }

        $output = $this->statements->fromStatement($first, $source->code, $source->offsetDelta);

        if (null === $output || !$this->leadingSeparator->matches($output, $catchVariable)) {
            return PreviousSeparatorContext::None;
        }

        if (null === $this->previousNewline->plan($previous, $source->code, $source->offsetDelta)) {
            return PreviousSeparatorContext::None;
        }

        if ($this->hasPlanFor($previous, $source)) {
            return PreviousSeparatorContext::Moved;
        }

        return $this->outputPresence->contains(array_values($try->stmts), $source->code, $source->offsetDelta)
            ? PreviousSeparatorContext::BlockedByTryOutput
            : PreviousSeparatorContext::None;
    }

    /**
     * @param list<Stmt> $statements
     * @return array{start: int, end: int}|null
     */
    private function directStatementRange(array $statements, FixtureContextSource $source): ?array
    {
        $start = null;
        $end = null;

        foreach ($statements as $offset => $statement) {

            if ($this->startsAt($statement, $source)) {
                $start = $offset;
            }

            if ($this->endsAt($statement, $source)) {
                $end = $offset;
            }
        }

        if (null === $start || null === $end || $end < $start) {
            return null;
        }

        return ['start' => $start, 'end' => $end];
    }

    private function startsAt(Stmt $statement, FixtureContextSource $source): bool
    {
        return $source->window->startOffset === $this->ast->filePosition($statement, 'startFilePos', $source->offsetDelta);
    }

    private function endsAt(Stmt $statement, FixtureContextSource $source): bool
    {
        $end = $this->ast->filePosition($statement, 'endFilePos', $source->offsetDelta);

        return null !== $end && $source->window->endOffset === $end + 1;
    }

    private function hasPlanFor(Stmt $statement, FixtureContextSource $source): bool
    {
        $start = $this->ast->filePosition($statement, 'startFilePos', $source->offsetDelta);
        $end = $this->ast->filePosition($statement, 'endFilePos', $source->offsetDelta);

        if (null === $start || null === $end) {
            return false;
        }

        return array_any($source->plans, fn($plan) => $plan->startOffset === $start && $plan->endOffset === $end + 1);
    }

    private function hasFollowingTraceOutput(?Stmt $following, string $catchVariable, FixtureContextSource $source): bool
    {
        $output = null === $following
            ? null
            : $this->statements->fromStatement($following, $source->code, $source->offsetDelta);

        $parts = $output?->parts->parts ?? [];

        return 1 === count($parts) && $this->parts->isExceptionTrace($parts[0], $catchVariable);
    }
}
