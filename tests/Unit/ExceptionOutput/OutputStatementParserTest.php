<?php

declare(strict_types=1);

namespace Tests\Unit\ExceptionOutput;

use InternalsCS\PhpAst;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputStatementParser;
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
            PHP);

        $parser = new OutputStatementParser();
        $echo = $parser->parts($statements[0]);
        $print = $parser->parts($statements[1]);
        $varDump = $parser->parts($statements[2]);

        self::assertNotNull($echo);
        self::assertNotNull($print);
        self::assertNotNull($varDump);
        self::assertTrue($echo->has(OutputPartKind::ExceptionMessage));
        self::assertTrue($echo->has(OutputPartKind::Newline));
        self::assertTrue($print->has(OutputPartKind::ExceptionMessage));
        self::assertTrue($varDump->has(OutputPartKind::ExceptionMessage));
    }

    public function testReturnsNullForNonOutputStatements(): void
    {
        $statements = $this->statements(<<<'PHP'
            <?php
            $message = $e->getMessage();
            PHP);

        self::assertNull(new OutputStatementParser()->parts($statements[0]));
    }

    /** @return list<\PhpParser\Node\Stmt> */
    private function statements(string $code): array
    {
        $parsed = new PhpAst()->parse($code);
        self::assertNotNull($parsed);

        return $parsed->statements;
    }
}
