<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Fixing;

use InternalsCS\PhpAst;
use PhpParser\Node\Stmt;

use function array_any;

final readonly class OutputStatementPresence
{
    public function __construct(
        private StatementFactory $statements = new StatementFactory(),
        private PhpAst $ast = new PhpAst(),
    ) {}

    /** @param list<Stmt> $statements */
    public function contains(array $statements, string $code, int $offsetDelta): bool
    {
        foreach ($statements as $statement) {

            if (null !== $this->statements->fromStatement($statement, $code, $offsetDelta)) {
                return true;
            }

            if (array_any($this->ast->childStatementLists($statement), fn($children) => $this->contains($children, $code, $offsetDelta))) {
                return true;
            }
        }

        return false;
    }
}
