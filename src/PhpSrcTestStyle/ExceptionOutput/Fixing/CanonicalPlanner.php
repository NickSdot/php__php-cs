<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing;

use InternalsCS\PhpAst;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\AdjacentClassThenMessageOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\AdjacentMessageThenNewlineOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\CatchTypeLabelOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\ClassMessageOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\ContextLabelOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\DescriptivePrefixOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\LocationOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\LocationWrapperOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\MessageOnlyOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\ParenthesizedClassLabelOutputRule;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

use function array_push;
use function array_values;
use function count;
use function is_string;
use function usort;

final readonly class CanonicalPlanner
{
    /** @var list<RewriteRule> */
    private array $rules;

    /** @param list<RewriteRule>|null $rules */
    public function __construct(
        private StatementFactory $statements = new StatementFactory(),
        private PhpAst $ast = new PhpAst(),
        ?array $rules = null,
    ) {
        $this->rules = $rules ?? self::defaultRules();
    }

    /** @return list<TextEdit> */
    public function plans(string $code): array
    {
        $parsed = $this->ast->parse($code);

        if (null === $parsed) {
            return [];
        }

        $plans = [];

        foreach ($this->ast->catchBlocks($parsed->statements) as $catch) {
            $catchVariable = $this->catchVariable($catch);

            if (null === $catchVariable) {
                continue;
            }

            array_push($plans, ...$this->plansForStatements(
                statements: array_values($catch->stmts),
                catchVariable: $catchVariable,
                catchTypes: $this->catchTypes($catch),
                code: $code,
                offsetDelta: $parsed->offsetDelta,
            ));
        }

        usort($plans, fn(TextEdit $a, TextEdit $b): int => $a->startOffset <=> $b->startOffset);

        return $plans;
    }

    /** @return list<RewriteRule> */
    private static function defaultRules(): array
    {
        return [
            new AdjacentClassThenMessageOutputRule(),
            new AdjacentMessageThenNewlineOutputRule(),
            new ParenthesizedClassLabelOutputRule(),
            new ContextLabelOutputRule(),
            new CatchTypeLabelOutputRule(),
            new DescriptivePrefixOutputRule(),
            new LocationWrapperOutputRule(),
            new LocationOutputRule(),
            new ClassMessageOutputRule(),
            new MessageOnlyOutputRule(),
        ];
    }

    /**
     * @param list<Stmt> $statements
     * @param list<string> $catchTypes
     * @return list<TextEdit>
     */
    private function plansForStatements(array $statements, string $catchVariable, array $catchTypes, string $code, int $offsetDelta): array
    {
        $plans = [];

        for ($i = 0; $i < count($statements); $i++) {
            $statement = $statements[$i];
            $nextStatement = $statements[$i + 1] ?? null;
            $output = $this->statements->fromStatement($statement, $code, $offsetDelta);

            if (null === $output) {
                array_push($plans, ...$this->plansForChildStatements($statement, $catchVariable, $catchTypes, $code, $offsetDelta));
                continue;
            }

            $nextOutput = null === $nextStatement ? null : $this->statements->fromStatement($nextStatement, $code, $offsetDelta);
            $result = $this->rewrite(new RewriteContext(
                catchVariable: $catchVariable,
                catchTypes: $catchTypes,
                statement: $output,
                nextStatement: $nextOutput,
            ));

            if (null !== $result) {
                $plans[] = $result->edit;
                $i += $result->consumedStatements - 1;
                continue;
            }

            array_push($plans, ...$this->plansForChildStatements($statement, $catchVariable, $catchTypes, $code, $offsetDelta));
        }

        return $plans;
    }

    /**
     * @param list<string> $catchTypes
     * @return list<TextEdit>
     */
    private function plansForChildStatements(
        Stmt $statement,
        string $catchVariable,
        array $catchTypes,
        string $code,
        int $offsetDelta,
    ): array {
        return $this->plansForStatements(
            statements: $this->ast->childStatements($statement),
            catchVariable: $catchVariable,
            catchTypes: $catchTypes,
            code: $code,
            offsetDelta: $offsetDelta,
        );
    }

    private function rewrite(RewriteContext $context): ?RewriteResult
    {
        foreach ($this->rules as $rule) {
            $result = $rule->rewrite($context);

            if (null !== $result) {
                return $result;
            }
        }

        return null;
    }

    private function catchVariable(Stmt\Catch_ $catch): ?string
    {
        if (!$catch->var instanceof Expr\Variable || !is_string($catch->var->name)) {
            return null;
        }

        return $catch->var->name;
    }

    /** @return list<string> */
    private function catchTypes(Stmt\Catch_ $catch): array
    {
        $types = [];

        foreach ($catch->types as $type) {
            $types[] = $type->toString();
        }

        return $types;
    }
}
