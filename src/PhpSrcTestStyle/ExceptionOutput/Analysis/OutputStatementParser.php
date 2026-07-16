<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis;

use InternalsCS\PhpAst;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

final readonly class OutputStatementParser
{
    public function __construct(
        private OutputExpressionParser $expressions = new OutputExpressionParser(),
        private PhpAst $ast = new PhpAst(),
    ) {}

    public function isOutputStatement(Stmt $statement): bool
    {
        return null !== $this->parts($statement);
    }

    public function parts(Stmt $statement): ?OutputParts
    {
        if ($statement instanceof Stmt\Echo_) {
            return $this->expressions->fromEcho($statement->exprs);
        }

        if (!$statement instanceof Stmt\Expression) {
            return null;
        }

        if ($statement->expr instanceof Expr\Print_) {
            return $this->expressions->fromPrint($statement->expr->expr);
        }

        if ($statement->expr instanceof Expr\FuncCall && $this->ast->isNamedCall($statement->expr, 'var_dump')) {
            return $this->expressions->fromVarDump($statement->expr->args);
        }

        return null;
    }
}
