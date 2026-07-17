<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;

use function array_push;
use function count;
use function implode;
use function is_string;
use function mb_strtolower;

final readonly class OutputExpressionParser
{
    /** @param list<Expr> $expression */
    public function fromEcho(array $expression): OutputParts
    {
        $parts = [];

        foreach ($expression as $expr) {
            array_push($parts, ...$this->parts($expr));
        }

        $shape = 'echo:' . $this->expressionListShape($expression);

        return new OutputParts($parts, $shape);
    }

    public function fromPrint(Expr $expr): OutputParts
    {
        return new OutputParts(
            parts: $this->parts($expr),
            shape: 'print:' . $this->shape($expr),
        );
    }

    /** @param list<Arg> $args */
    public function fromVarDump(array $args): OutputParts
    {
        $parts = [];

        foreach ($args as $arg) {
            array_push($parts, ...$this->parts($arg->value));
        }

        return new OutputParts($parts, 'var_dump');
    }

    /** @return list<OutputPart> */
    private function parts(Expr|InterpolatedStringPart $expr): array
    {
        if ($expr instanceof InterpolatedStringPart) {
            return [OutputPart::literal($expr->value)];
        }

        if ($expr instanceof Expr\BinaryOp\Concat) {
            return [
                ...$this->parts($expr->left),
                ...$this->parts($expr->right),
            ];
        }

        if ($expr instanceof Scalar\String_) {
            return [OutputPart::literal($expr->value)];
        }

        if ($expr instanceof Scalar\InterpolatedString) {
            return $this->interpolatedStringParts($expr);
        }

        if ($this->isPhpEol($expr)) {
            return [OutputPart::newline()];
        }

        $part = $this->exceptionClassPart($expr);

        if (null !== $part) {
            return [$part];
        }

        $part = $this->exceptionMethodPart($expr);

        if (null !== $part) {
            return [$part];
        }

        $variable = $this->variableName($expr);

        if (null !== $variable) {
            return [OutputPart::otherVariable($variable)];
        }

        return [OutputPart::unknown($expr->getType())];
    }

    /** @return list<OutputPart> */
    private function interpolatedStringParts(Scalar\InterpolatedString $expr): array
    {
        $parts = [];

        foreach ($expr->parts as $part) {
            array_push($parts, ...$this->parts($part));
        }

        return $parts;
    }

    private function exceptionClassPart(Expr $expr): ?OutputPart
    {
        if ($expr instanceof Expr\ClassConstFetch && $this->nameEquals($expr->name, 'class')) {
            $variable = $this->variableName($expr->class);

            return null === $variable ? null : OutputPart::exceptionClass($variable, 'static_class');
        }

        if (!$expr instanceof Expr\FuncCall || !$this->nameEquals($expr->name, 'get_class')) {
            return null;
        }

        $firstArg = $expr->args[0]->value ?? null;
        $variable = $firstArg instanceof Expr ? $this->variableName($firstArg) : null;

        return null === $variable ? null : OutputPart::exceptionClass($variable, 'get_class');
    }

    private function exceptionMethodPart(Expr $expr): ?OutputPart
    {
        if (!$expr instanceof Expr\MethodCall) {
            return null;
        }

        $variable = $this->variableName($expr->var);
        $method = $this->name($expr->name);

        if (null === $variable || null === $method) {
            return null;
        }

        return match (mb_strtolower($method)) {
            'getmessage' => OutputPart::exceptionMessage($variable),
            'getfile' => OutputPart::exceptionFile($variable),
            'getline' => OutputPart::exceptionLine($variable),
            default => null,
        };
    }

    private function isPhpEol(Expr $expr): bool
    {
        return $expr instanceof Expr\ConstFetch
            && $this->nameEquals($expr->name, 'PHP_EOL');
    }

    private function shape(?Expr $expr): string
    {
        if (null === $expr) {
            return 'empty';
        }

        if ($expr instanceof Expr\BinaryOp\Concat) {
            return 'concat';
        }

        if ($expr instanceof Scalar\InterpolatedString) {
            return 'interpolated';
        }

        if ($expr instanceof Scalar\String_) {
            return 'literal';
        }

        if ($expr instanceof Expr\FuncCall) {
            return 'function_call';
        }

        if ($expr instanceof Expr\MethodCall) {
            return 'method_call';
        }

        if ($expr instanceof Expr\ClassConstFetch) {
            return 'class_const';
        }

        return $expr->getType();
    }

    /** @param list<Expr> $expressions */
    private function expressionListShape(array $expressions): string
    {
        if ([] === $expressions) {
            return 'empty';
        }

        if (1 === count($expressions)) {
            return $this->shape($expressions[0]);
        }

        $shape = [];

        foreach ($expressions as $expr) {
            $shape[] = $this->shape($expr);
        }

        return 'comma(' . implode(',', $shape) . ')';
    }

    private function variableName(Node $node): ?string
    {
        if (!$node instanceof Expr\Variable || !is_string($node->name)) {
            return null;
        }

        return $node->name;
    }

    private function name(Node $node): ?string
    {
        if ($node instanceof Identifier || $node instanceof Name) {
            return $node->toString();
        }

        return null;
    }

    private function nameEquals(Node $node, string $expected): bool
    {
        $name = $this->name($node);

        return null !== $name && mb_strtolower($name) === mb_strtolower($expected);
    }
}
