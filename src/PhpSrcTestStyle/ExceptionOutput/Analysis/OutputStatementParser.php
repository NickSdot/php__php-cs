<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis;

use InternalsCS\PhpAst;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

use function array_values;

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
            return $this->expressions->fromEcho(array_values($statement->exprs));
        }

        if (!$statement instanceof Stmt\Expression) {
            return null;
        }

        if ($statement->expr instanceof Expr\Print_) {
            return $this->expressions->fromPrint($statement->expr->expr);
        }

        if ($statement->expr instanceof Expr\FuncCall && $this->ast->isNamedCall($statement->expr, 'var_dump')) {
            $args = [];

            foreach ($statement->expr->args as $arg) {
                if (!$arg instanceof Arg) {
                    return null;
                }

                $args[] = $arg;
            }

            return $this->expressions->fromVarDump($args);
        }

        if ($statement->expr instanceof Expr\FuncCall && $this->ast->isNamedCall($statement->expr, 'print_r')) {
            $firstArg = $statement->expr->args[0]->value ?? null;

            return $firstArg instanceof Expr ? $this->expressions->fromPrintR($firstArg) : null;
        }

        return null;
    }
}
