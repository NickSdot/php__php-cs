<?php

declare(strict_types=1);

namespace InternalsCS;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\Parser;
use PhpParser\ParserFactory;

use function array_push;
use function array_values;
use function get_object_vars;
use function is_array;
use function is_int;
use function mb_strlen;
use function mb_strtolower;
use function str_contains;

final readonly class PhpAst
{
    private Parser $parser;

    public function __construct(?Parser $parser = null)
    {
        $this->parser = $parser ?? new ParserFactory()->createForNewestSupportedVersion();
    }

    public function parse(string $code): ?ParsedPhpCode
    {
        $prefix = str_contains($code, '<?') ? '' : "<?php\n";

        try {
            $statements = $this->parser->parse($prefix . $code) ?? [];

            return new ParsedPhpCode(
                statements: array_values($statements),
                offsetDelta: mb_strlen($prefix),
            );
        } catch (Error) {
            return null;
        }
    }

    /**
     * @param list<Stmt> $statements
     * @return list<Stmt\Catch_>
     */
    public function catchBlocks(array $statements): array
    {
        $catches = [];

        foreach ($statements as $statement) {
            if (!$statement instanceof Stmt\TryCatch) {
                array_push($catches, ...$this->catchBlocks($this->childStatements($statement)));
                continue;
            }

            foreach ($statement->catches as $catch) {
                $catches[] = $catch;
            }

            array_push($catches, ...$this->catchBlocks(array_values($statement->stmts)));

            if (null !== $statement->finally) {
                array_push($catches, ...$this->catchBlocks(array_values($statement->finally->stmts)));
            }
        }

        return $catches;
    }

    /** @return list<Stmt> */
    public function childStatements(Stmt $statement): array
    {
        $children = [];

        foreach (get_object_vars($statement) as $value) {
            $this->appendChildStatements($children, $value);
        }

        return $children;
    }

    /** @return list<list<Stmt>> */
    public function childStatementLists(Stmt $statement): array
    {
        if ($statement instanceof Stmt\Namespace_) {
            return [array_values($statement->stmts)];
        }

        if ($statement instanceof Stmt\ClassMethod || $statement instanceof Stmt\Function_) {
            return [array_values($statement->stmts ?? [])];
        }

        if ($statement instanceof Stmt\If_) {
            $lists = [array_values($statement->stmts)];

            foreach ($statement->elseifs as $elseif) {
                $lists[] = array_values($elseif->stmts);
            }

            if (null !== $statement->else) {
                $lists[] = array_values($statement->else->stmts);
            }

            return $lists;
        }

        if ($statement instanceof Stmt\Foreach_ || $statement instanceof Stmt\For_ || $statement instanceof Stmt\While_ || $statement instanceof Stmt\Do_) {
            return [array_values($statement->stmts)];
        }

        if ($statement instanceof Stmt\Switch_) {
            $lists = [];

            foreach ($statement->cases as $case) {
                $lists[] = array_values($case->stmts);
            }

            return $lists;
        }

        return [];
    }

    public function filePosition(Node $node, string $attribute, int $offsetDelta): ?int
    {
        $position = $node->getAttribute($attribute);

        if (!is_int($position)) {
            return null;
        }

        return $position - $offsetDelta;
    }

    public function isNamedCall(Expr\FuncCall $call, string $name): bool
    {
        return $call->name instanceof Node\Name
            && mb_strtolower($call->name->toString()) === mb_strtolower($name);
    }

    /** @param list<Stmt> $children */
    private function appendChildStatements(array &$children, mixed $value): void
    {
        if ($value instanceof Stmt) {
            $children[] = $value;
            return;
        }

        if ($value instanceof Node) {
            foreach (get_object_vars($value) as $child) {
                $this->appendChildStatements($children, $child);
            }

            return;
        }

        if (!is_array($value)) {
            return;
        }

        foreach ($value as $item) {
            $this->appendChildStatements($children, $item);
        }
    }
}
