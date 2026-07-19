<?php

declare(strict_types=1);

namespace Tests\Unit\ExceptionOutput;

use InternalsCS\Fixers\ExceptionOutput\Analysis\ExpressionSource;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputStatementParser;
use InternalsCS\PhpAst;
use PHPUnit\Framework\TestCase;

final class OutputStatementParserTest extends TestCase
{
    public function testParsesEchoPrintAndVarDumpOutputStatements(): void
    {
        $statements = $this->statements(<<<'PHP'
            <?php
            echo $e->getMessage(), "\n";
            print $e->getMessage();
            var_dump($e->getMessage());
            echo $e->getTraceAsString();
            PHP);

        $parser = new OutputStatementParser();
        $echo = $parser->parts($statements[0]);
        $print = $parser->parts($statements[1]);
        $varDump = $parser->parts($statements[2]);
        $trace = $parser->parts($statements[3]);

        self::assertNotNull($echo);
        self::assertNotNull($print);
        self::assertNotNull($varDump);
        self::assertNotNull($trace);
        self::assertTrue($echo->has(OutputPartKind::ExceptionMessage));
        self::assertTrue($echo->has(OutputPartKind::Newline));
        self::assertTrue($print->has(OutputPartKind::ExceptionMessage));
        self::assertTrue($varDump->has(OutputPartKind::ExceptionMessage));
        self::assertTrue($trace->has(OutputPartKind::ExceptionTrace));
    }

    public function testReturnsNullForNonOutputStatements(): void
    {
        $statements = $this->statements(<<<'PHP'
            <?php
            $message = $e->getMessage();
            PHP);

        self::assertNull(new OutputStatementParser()->parts($statements[0]));
    }

    public function testPreservesUnknownExpressionSourceWhenCodeIsProvided(): void
    {
        $code = <<<'PHP'
            <?php
            echo "{$rf->getName()}: {$e->getMessage()}\n";
            PHP;

        $parsed = new PhpAst()->parse($code);
        self::assertNotNull($parsed);

        $parts = new OutputStatementParser()->parts($parsed->statements[0], new ExpressionSource($code, $parsed->offsetDelta));
        self::assertNotNull($parts);

        self::assertSame(OutputPartKind::OtherExpression, $parts->parts[0]->kind);
        self::assertSame('$rf->getName()', $parts->parts[0]->source);
    }

    /** @return list<\PhpParser\Node\Stmt> */
    private function statements(string $code): array
    {
        $parsed = new PhpAst()->parse($code);
        self::assertNotNull($parsed);

        return $parsed->statements;
    }
}
