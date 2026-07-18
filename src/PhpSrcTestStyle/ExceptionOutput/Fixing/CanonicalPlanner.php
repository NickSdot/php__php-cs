<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing;

use InternalsCS\PhpAst;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputFamily;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\AdjacentClassThenMessageOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\AdjacentMessageThenNewlineOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\CatchTypeLabelOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\ClassMessageOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\ContextLabelOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\DescriptivePrefixOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\DynamicContextPrefixOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\LeadingSeparatorOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\LocationOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\LocationWrapperOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\MarkerPrefixOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\MessageBeforeTraceOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\MessageOnlyOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\MixedVarDumpMessageOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\ParenthesizedClassLabelOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\PreservedPrefixOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\QuotedClassMessageOutputRule;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\Rules\SameStatementTraceOutputRule;
use InternalsCS\RewriteResult;
use InternalsCS\TextEdit;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;

use function array_any;
use function array_push;
use function array_values;
use function count;
use function is_string;
use function mb_rtrim;
use function mb_strlen;
use function mb_substr;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function usort;

final readonly class CanonicalPlanner
{
    /** @var list<RewriteRule> */
    private array $rules;

    /** @param list<RewriteRule>|null $rules */
    public function __construct(
        private StatementFactory $statements = new StatementFactory(),
        private PhpAst $ast = new PhpAst(),
        private CanonicalStatementBuilder $builder = new CanonicalStatementBuilder(),
        private LeadingSeparatorOutput $leadingSeparator = new LeadingSeparatorOutput(),
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

        $previousSeparatorPlans = $this->previousSeparatorPlans($parsed->statements, $code, $parsed->offsetDelta);
        $plans = $this->withoutOverlaps($plans, $previousSeparatorPlans);
        array_push($plans, ...$previousSeparatorPlans);

        $followingNewlinePlans = $this->followingNewlinePlans($parsed->statements, $code, $parsed->offsetDelta);
        $plans = $this->withoutOverlaps($plans, $followingNewlinePlans);
        array_push($plans, ...$followingNewlinePlans);

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
            new DynamicContextPrefixOutputRule(),
            new MarkerPrefixOutputRule(),
            new MixedVarDumpMessageOutputRule(),
            new PreservedPrefixOutputRule(),
            new LeadingSeparatorOutputRule(),
            new MessageBeforeTraceOutputRule(),
            new SameStatementTraceOutputRule(),
            new LocationWrapperOutputRule(),
            new LocationOutputRule(),
            new QuotedClassMessageOutputRule(),
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

    /**
     * @param list<Stmt> $statements
     * @return list<TextEdit>
     */
    private function previousSeparatorPlans(array $statements, string $code, int $offsetDelta): array
    {
        $plans = [];

        for ($i = 0; $i < count($statements); $i++) {
            $statement = $statements[$i];
            $previous = $statements[$i - 1] ?? null;

            if (!$statement instanceof Stmt\TryCatch) {
                array_push($plans, ...$this->previousSeparatorPlansForChildren($statement, $code, $offsetDelta));
                continue;
            }

            if (null !== $previous && !$this->containsOutputStatement(array_values($statement->stmts), $code, $offsetDelta)) {
                foreach ($statement->catches as $catch) {
                    array_push($plans, ...$this->previousSeparatorPlansForCatch($catch, $previous, $code, $offsetDelta));
                }
            }

            array_push($plans, ...$this->previousSeparatorPlans(array_values($statement->stmts), $code, $offsetDelta));

            foreach ($statement->catches as $catch) {
                array_push($plans, ...$this->previousSeparatorPlans(array_values($catch->stmts), $code, $offsetDelta));
            }

            if (null !== $statement->finally) {
                array_push($plans, ...$this->previousSeparatorPlans(array_values($statement->finally->stmts), $code, $offsetDelta));
            }
        }

        return $plans;
    }

    /** @param list<Stmt> $statements */
    private function containsOutputStatement(array $statements, string $code, int $offsetDelta): bool
    {
        foreach ($statements as $statement) {
            if (null !== $this->statements->fromStatement($statement, $code, $offsetDelta)) {
                return true;
            }

            foreach ($this->childStatementLists($statement) as $children) {
                if ($this->containsOutputStatement($children, $code, $offsetDelta)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return list<TextEdit> */
    private function previousSeparatorPlansForChildren(Stmt $statement, string $code, int $offsetDelta): array
    {
        $plans = [];

        foreach ($this->childStatementLists($statement) as $statements) {
            array_push($plans, ...$this->previousSeparatorPlans($statements, $code, $offsetDelta));
        }

        return $plans;
    }

    /** @return list<TextEdit> */
    private function previousSeparatorPlansForCatch(Stmt\Catch_ $catch, Stmt $previous, string $code, int $offsetDelta): array
    {
        $catchVariable = $this->catchVariable($catch);

        if (null === $catchVariable) {
            return [];
        }

        $statements = array_values($catch->stmts);
        $firstStatement = $statements[0] ?? null;

        if (null === $firstStatement) {
            return [];
        }

        $output = $this->statements->fromStatement($firstStatement, $code, $offsetDelta);
        $previousEdit = $this->previousTrailingNewlineEdit($previous, $code, $offsetDelta);

        if (null === $output || null === $previousEdit || !$this->leadingSeparator->matches($output, $catchVariable)) {
            return [];
        }

        return [
            $previousEdit,
            new TextEdit(
                startOffset: $output->startOffset,
                endOffset: $output->endOffset,
                line: $output->line,
                replacement: $this->builder->build($catchVariable, $output->parts),
            ),
        ];
    }

    private function previousTrailingNewlineEdit(Stmt $statement, string $code, int $offsetDelta): ?TextEdit
    {
        if (!$statement instanceof Stmt\Echo_) {
            return null;
        }

        $output = $this->statements->fromStatement($statement, $code, $offsetDelta);

        if (null === $output || $output->parts->has(OutputPartKind::Newline)) {
            return null;
        }

        $source = mb_substr($code, $output->startOffset, $output->endOffset - $output->startOffset, '8bit');
        $trimmed = mb_rtrim($source);

        if (!str_ends_with($trimmed, ';')) {
            return null;
        }

        $tail = mb_substr($source, mb_strlen($trimmed, '8bit'), null, '8bit');
        $replacement = mb_substr($trimmed, 0, -1, '8bit') . ', \PHP_EOL;' . $tail;

        return new TextEdit(
            startOffset: $output->startOffset,
            endOffset: $output->endOffset,
            line: $output->line,
            replacement: $replacement,
        );
    }

    /**
     * @param list<Stmt> $statements
     * @return list<TextEdit>
     */
    private function followingNewlinePlans(array $statements, string $code, int $offsetDelta): array
    {
        $plans = [];

        for ($i = 0; $i < count($statements); $i++) {
            $statement = $statements[$i];
            $following = $statements[$i + 1] ?? null;

            if (!$statement instanceof Stmt\TryCatch) {
                array_push($plans, ...$this->followingNewlinePlansForChildren($statement, $code, $offsetDelta));
                continue;
            }

            if (null !== $following) {
                foreach ($statement->catches as $catch) {
                    array_push($plans, ...$this->followingNewlinePlansForCatch($catch, $following, $code, $offsetDelta));
                }
            }

            array_push($plans, ...$this->followingNewlinePlans(array_values($statement->stmts), $code, $offsetDelta));

            foreach ($statement->catches as $catch) {
                array_push($plans, ...$this->followingNewlinePlans(array_values($catch->stmts), $code, $offsetDelta));
            }

            if (null !== $statement->finally) {
                array_push($plans, ...$this->followingNewlinePlans(array_values($statement->finally->stmts), $code, $offsetDelta));
            }
        }

        return $plans;
    }

    /** @return list<TextEdit> */
    private function followingNewlinePlansForChildren(Stmt $statement, string $code, int $offsetDelta): array
    {
        $plans = [];

        foreach ($this->childStatementLists($statement) as $statements) {
            array_push($plans, ...$this->followingNewlinePlans($statements, $code, $offsetDelta));
        }

        return $plans;
    }

    /** @return list<list<Stmt>> */
    private function childStatementLists(Stmt $statement): array
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

    /** @return list<TextEdit> */
    private function followingNewlinePlansForCatch(Stmt\Catch_ $catch, Stmt $following, string $code, int $offsetDelta): array
    {
        $catchVariable = $this->catchVariable($catch);

        if (null === $catchVariable) {
            return [];
        }

        $statements = array_values($catch->stmts);
        $lastStatement = $statements[count($statements) - 1] ?? null;

        if (null === $lastStatement) {
            return [];
        }

        $output = $this->statements->fromStatement($lastStatement, $code, $offsetDelta);
        $followingReplacement = $this->followingWithoutLeadingNewline($following);

        if (null === $output || null === $followingReplacement || !$this->isSafeTrailingCatchOutput($output, $catchVariable)) {
            return [];
        }

        $followingStart = $this->ast->filePosition($following, 'startFilePos', $offsetDelta);
        $followingEnd = $this->ast->filePosition($following, 'endFilePos', $offsetDelta);

        if (null === $followingStart || null === $followingEnd) {
            return [];
        }

        return [
            new TextEdit(
                startOffset: $output->startOffset,
                endOffset: $output->endOffset,
                line: $output->line,
                replacement: new CanonicalStatementBuilder()->build($catchVariable, $output->parts),
            ),
            new TextEdit(
                startOffset: $followingStart,
                endOffset: $followingEnd + 1,
                line: $following->getStartLine(),
                replacement: $followingReplacement,
            ),
        ];
    }

    private function isSafeTrailingCatchOutput(Statement $statement, string $catchVariable): bool
    {
        if ('var_dump' === $statement->parts->shape) {
            return false;
        }

        if ($statement->parts->has(OutputPartKind::Newline)) {
            return false;
        }

        return new CanonicalRewriteSafety()->canRewrite(
            $statement,
            $catchVariable,
            OutputFamily::MessageOnly,
            OutputFamily::ClassMessage,
            OutputFamily::ClassMessageLocation,
        );
    }

    private function followingWithoutLeadingNewline(Stmt $statement): ?string
    {
        if (!$statement instanceof Stmt\Echo_ || 1 !== count($statement->exprs)) {
            return null;
        }

        $expr = $statement->exprs[0];

        if (!$expr instanceof Scalar\String_ || !str_starts_with($expr->value, "\n")) {
            return null;
        }

        return 'echo "' . $this->doubleQuoted(mb_substr($expr->value, 1)) . '";';
    }

    private function doubleQuoted(string $value): string
    {
        return str_replace(
            ["\\", "\n", "\r", "\t", '"', '$'],
            ["\\\\", "\\n", "\\r", "\\t", '\\"', '\\$'],
            $value,
        );
    }

    /**
     * @param list<TextEdit> $plans
     * @param list<TextEdit> $preferred
     * @return list<TextEdit>
     */
    private function withoutOverlaps(array $plans, array $preferred): array
    {
        $kept = [];

        foreach ($plans as $plan) {
            if ($this->overlapsAny($plan, $preferred)) {
                continue;
            }

            $kept[] = $plan;
        }

        return $kept;
    }

    /** @param list<TextEdit> $edits */
    private function overlapsAny(TextEdit $edit, array $edits): bool
    {
        return array_any($edits, fn($other) => $edit->startOffset < $other->endOffset && $other->startOffset < $edit->endOffset);
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
