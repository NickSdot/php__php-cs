<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Analysis;

use InternalsCS\PhpAst;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

use function array_all;
use function array_values;
use function count;
use function is_string;
use function mb_substr;
use function mb_trim;
use function usort;

final readonly class StatementWindowFinder
{
    public function __construct(
        private PhpAst $ast = new PhpAst(),
        private OutputStatementParser $outputs = new OutputStatementParser(),
    ) {}

    /** @return list<Window> */
    public function find(string $code): array
    {
        $parsed = $this->ast->parse($code);

        if (null === $parsed) {
            return [];
        }

        $windows = [];

        foreach ($this->ast->catchBlocks($parsed->statements) as $catch) {
            $catchVariable = $this->catchVariable($catch);

            if (null === $catchVariable) {
                continue;
            }

            $windows = [
                ...$windows,
                ...$this->windowsForStatements(
                    statements: array_values($catch->stmts),
                    code: $code,
                    offsetDelta: $parsed->offsetDelta,
                    catchVariable: $catchVariable,
                    catchTypes: $this->catchTypes($catch),
                ),
            ];
        }

        usort($windows, fn(Window $a, Window $b): int => $a->startOffset <=> $b->startOffset);

        return $windows;
    }

    /**
     * @param list<Stmt> $statements
     * @param list<string> $catchTypes
     * @return list<Window>
     */
    private function windowsForStatements(array $statements, string $code, int $offsetDelta, string $catchVariable, array $catchTypes): array
    {
        $windows = [];

        for ($i = 0; $i < count($statements); $i++) {
            $statement = $statements[$i];
            $window = $this->window($statement, $code, $offsetDelta, $catchVariable, $catchTypes);

            if (null === $window) {
                $windows = [
                    ...$windows,
                    ...$this->windowsForChildStatements($statement, $code, $offsetDelta, $catchVariable, $catchTypes),
                ];
                continue;
            }

            $nextStatement = $statements[$i + 1] ?? null;
            $nextWindow = null === $nextStatement ? null : $this->window($nextStatement, $code, $offsetDelta, $catchVariable, $catchTypes);
            $adjacent = null === $nextWindow ? null : $this->adjacentWindow($window, $nextWindow, $code);

            if (null !== $adjacent) {
                $windows[] = $adjacent;
                $i++;
                continue;
            }

            if ($window->parts->has(OutputPartKind::ExceptionMessage)) {
                $windows[] = $window;
            }
        }

        return $windows;
    }

    /**
     * @param list<string> $catchTypes
     * @return list<Window>
     */
    private function windowsForChildStatements(Stmt $statement, string $code, int $offsetDelta, string $catchVariable, array $catchTypes): array
    {
        $windows = [];

        foreach ($this->ast->childStatementLists($statement) as $statements) {
            $windows = [
                ...$windows,
                ...$this->windowsForStatements($statements, $code, $offsetDelta, $catchVariable, $catchTypes),
            ];
        }

        return $windows;
    }

    private function adjacentWindow(Window $current, Window $next, string $code): ?Window
    {
        if (!$this->isMessageThenNewline($current, $next) && !$this->isClassThenMessage($current, $next)) {
            return null;
        }

        return $this->combinedWindow($current, $next, $code);
    }

    private function isClassThenMessage(Window $current, Window $next): bool
    {
        return $current->parts->has(OutputPartKind::ExceptionClass)
            && !$current->parts->has(OutputPartKind::ExceptionMessage)
            && $next->parts->has(OutputPartKind::ExceptionMessage);
    }

    private function isMessageThenNewline(Window $current, Window $next): bool
    {
        if (!$current->parts->has(OutputPartKind::ExceptionMessage) || $current->parts->has(OutputPartKind::Newline)) {
            return false;
        }

        if ([] === $next->parts->parts) {
            return false;
        }

        return array_all($next->parts->parts, fn($part) => OutputPartKind::Newline === $part->kind);
    }

    private function combinedWindow(Window $current, Window $next, string $code): Window
    {
        return new Window(
            startOffset: $current->startOffset,
            endOffset: $next->endOffset,
            startLine: $current->startLine,
            statement: mb_trim(mb_substr($code, $current->startOffset, $next->endOffset - $current->startOffset, '8bit')),
            parts: new OutputParts(
                parts: [
                    ...$current->parts->parts,
                    ...$next->parts->parts,
                ],
                shape: 'adjacent(' . $current->parts->shape . ',' . $next->parts->shape . ')',
            ),
            catchVariable: $current->catchVariable,
            catchTypes: $current->catchTypes,
        );
    }

    /** @param list<string> $catchTypes */
    private function window(Stmt $statement, string $code, int $offsetDelta, string $catchVariable, array $catchTypes): ?Window
    {
        $start = $this->ast->filePosition($statement, 'startFilePos', $offsetDelta);
        $end = $this->ast->filePosition($statement, 'endFilePos', $offsetDelta);

        if (null === $start || null === $end || $start < 0 || $end < $start) {
            return null;
        }

        $parts = $this->outputs->parts($statement, new ExpressionSource($code, $offsetDelta));

        if (null === $parts) {
            return null;
        }

        return new Window(
            startOffset: $start,
            endOffset: $end + 1,
            startLine: $statement->getStartLine(),
            statement: mb_trim(mb_substr($code, $start, $end - $start + 1, '8bit')),
            parts: $parts,
            catchVariable: $catchVariable,
            catchTypes: $catchTypes,
        );
    }

    private function catchVariable(Stmt\Catch_ $catch): ?string
    {
        if (!$catch->var instanceof Expr\Variable || !is_string($catch->var->name)) {
            return null;
        }

        return $catch->var->name;
    }

    /** @return list<string> */
    private function catchTypes(Stmt\Catch_ $catch): array
    {
        $types = [];

        foreach ($catch->types as $type) {
            $types[] = $type->toString();
        }

        return $types;
    }
}
