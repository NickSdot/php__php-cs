<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis;

use InternalsCS\PhpAst;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

use function array_values;
use function mb_substr;
use function mb_trim;
use function usort;

final readonly class StatementWindowFinder
{
    public function __construct(
        private PhpAst $ast = new PhpAst(),
        private OutputStatementParser $outputs = new OutputStatementParser(),
        private NodeFinder $nodes = new NodeFinder(),
    ) {}

    /** @return list<Window> */
    public function find(string $code): array
    {
        $parsed = $this->ast->parse($code);

        if (null === $parsed) {
            return [];
        }

        $windows = [];

        foreach ($this->catchOutputStatements($parsed->statements) as $statement) {
            $window = $this->window($statement, $code, $parsed->offsetDelta);

            if (null === $window || !$window->parts->has(OutputPartKind::ExceptionMessage)) {
                continue;
            }

            $windows[] = $window;
        }

        usort($windows, fn(Window $a, Window $b): int => $a->startOffset <=> $b->startOffset);

        return $windows;
    }

    /**
     * @param list<Stmt> $statements
     * @return list<Stmt>
     */
    private function catchOutputStatements(array $statements): array
    {
        $outputs = [];

        foreach ($this->ast->catchBlocks($statements) as $catch) {
            foreach ($this->outputStatements(array_values($catch->stmts)) as $statement) {
                $key = $statement->getStartFilePos() . ':' . $statement->getEndFilePos();
                $outputs[$key] = $statement;
            }
        }

        return array_values($outputs);
    }

    /**
     * @param list<Stmt> $statements
     * @return list<Stmt>
     */
    private function outputStatements(array $statements): array
    {
        $nodes = $this->nodes->find(
            $statements,
            fn(Node $node): bool => $node instanceof Stmt
                && $this->outputs->isOutputStatement($node),
        );
        $statements = [];

        foreach ($nodes as $node) {
            if ($node instanceof Stmt) {
                $statements[] = $node;
            }
        }

        return $statements;
    }

    private function window(Stmt $statement, string $code, int $offsetDelta): ?Window
    {
        $start = $this->ast->filePosition($statement, 'startFilePos', $offsetDelta);
        $end = $this->ast->filePosition($statement, 'endFilePos', $offsetDelta);

        if (null === $start || null === $end || $start < 0 || $end < $start) {
            return null;
        }

        $parts = $this->outputs->parts($statement);

        if (null === $parts) {
            return null;
        }

        return new Window(
            startOffset: $start,
            endOffset: $end,
            startLine: $statement->getStartLine(),
            endLine: $statement->getEndLine(),
            statement: mb_trim(mb_substr($code, $start, $end - $start + 1, '8bit')),
            parts: $parts,
        );
    }
}
