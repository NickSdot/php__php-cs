<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing;

use InternalsCS\PhpAst;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\Classifier;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\ExpressionSource;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputStatementParser;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\Window;
use PhpParser\Node\Stmt;

use function mb_strrpos;
use function mb_substr;
use function mb_trim;
use function preg_match;

final readonly class StatementFactory
{
    public function __construct(
        private OutputStatementParser $outputs = new OutputStatementParser(),
        private Classifier $classifier = new Classifier(),
        private PhpAst $ast = new PhpAst(),
    ) {}

    public function fromStatement(Stmt $statement, string $code, int $offsetDelta): ?Statement
    {
        $parts = $this->outputs->parts($statement, new ExpressionSource($code, $offsetDelta));

        if (null === $parts) {
            return null;
        }

        $start = $this->ast->filePosition($statement, 'startFilePos', $offsetDelta);
        $end = $this->ast->filePosition($statement, 'endFilePos', $offsetDelta);

        if (null === $start || null === $end || $start < 0 || $end < $start) {
            return null;
        }

        $classification = $this->classifier->classify(new Window(
            startOffset: $start,
            startLine: $statement->getStartLine(),
            statement: mb_trim(mb_substr($code, $start, $end - $start + 1, '8bit')),
            parts: $parts,
        ));

        return new Statement(
            startOffset: $start,
            endOffset: $end + 1,
            line: $statement->getStartLine(),
            indent: $this->indent($code, $start),
            parts: $parts,
            classification: $classification,
        );
    }

    private function indent(string $code, int $start): string
    {
        $beforeStatement = mb_substr($code, 0, $start, '8bit');
        $lineStart = mb_strrpos($beforeStatement, "\n", 0, '8bit');
        $indentStart = false === $lineStart ? 0 : $lineStart + 1;
        $indent = mb_substr($code, $indentStart, $start - $indentStart, '8bit');

        return 1 === preg_match('/^[ \t]*$/', $indent) ? $indent : '';
    }
}
