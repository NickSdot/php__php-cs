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

        self::assertTrue($parser->parts($statements[0])?->has(OutputPartKind::ExceptionMessage));
        self::assertTrue($parser->parts($statements[0])?->has(OutputPartKind::Newline));
        self::assertTrue($parser->parts($statements[1])?->has(OutputPartKind::ExceptionMessage));
        self::assertTrue($parser->parts($statements[2])?->has(OutputPartKind::ExceptionMessage));
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
