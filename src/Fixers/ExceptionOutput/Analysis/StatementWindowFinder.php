<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Analysis;

use InternalsCS\PhpAst;
use PhpParser\Node\Stmt;

use function array_values;
use function count;
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
            $windows = [
                ...$windows,
                ...$this->windowsForStatements(array_values($catch->stmts), $code, $parsed->offsetDelta),
            ];
        }

        usort($windows, fn(Window $a, Window $b): int => $a->startOffset <=> $b->startOffset);

        return $windows;
    }

    /**
     * @param list<Stmt> $statements
     * @return list<Window>
     */
    private function windowsForStatements(array $statements, string $code, int $offsetDelta): array
    {
        $windows = [];

        for ($i = 0; $i < count($statements); $i++) {
            $statement = $statements[$i];
            $window = $this->window($statement, $code, $offsetDelta);

            if (null === $window) {
                $windows = [
                    ...$windows,
                    ...$this->windowsForChildStatements($statement, $code, $offsetDelta),
                ];
                continue;
            }

            $nextStatement = $statements[$i + 1] ?? null;
            $nextWindow = null === $nextStatement ? null : $this->window($nextStatement, $code, $offsetDelta);
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

    /** @return list<Window> */
    private function windowsForChildStatements(Stmt $statement, string $code, int $offsetDelta): array
    {
        $windows = [];

        foreach ($this->ast->childStatementLists($statement) as $statements) {
            $windows = [
                ...$windows,
                ...$this->windowsForStatements($statements, $code, $offsetDelta),
            ];
        }

        return $windows;
    }

    private function adjacentWindow(Window $current, Window $next, string $code): ?Window
    {
        if (!$current->parts->has(OutputPartKind::ExceptionClass)) {
            return null;
        }

        if ($current->parts->has(OutputPartKind::ExceptionMessage)) {
            return null;
        }

        if (!$next->parts->has(OutputPartKind::ExceptionMessage)) {
            return null;
        }

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
        );
    }

    private function window(Stmt $statement, string $code, int $offsetDelta): ?Window
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
        );
    }
}
