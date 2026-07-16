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
            return new ParsedPhpCode(
                statements: $this->parser->parse($prefix . $code) ?? [],
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

            array_push($catches, ...$this->catchBlocks($statement->stmts));
            array_push($catches, ...$this->catchBlocks($statement->finally?->stmts ?? []));
        }

        return $catches;
    }

    /** @return list<Stmt> */
    public function childStatements(Stmt $statement): array
    {
        $children = [];

        foreach ($statement->getSubNodeNames() as $name) {
            $this->appendChildStatements($children, $statement->$name);
        }

        return $children;
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
            foreach ($value->getSubNodeNames() as $name) {
                $this->appendChildStatements($children, $value->$name);
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
