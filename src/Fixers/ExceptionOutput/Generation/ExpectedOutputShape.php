<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Generation;

use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputExpectation;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\Fixers\ExceptionOutput\Analysis\Window;
use InternalsCS\PhpAst;
use InternalsCS\PhpSrcTestStyle\PhptSection;
use PhpParser\Node\Stmt;

use function mb_strtolower;
use function mb_trim;
use function str_replace;

final readonly class ExpectedOutputShape
{
    public function __construct(
        private PhpAst $ast = new PhpAst(),
    ) {}

    public function key(Window $window, PhptSection $code, ?PhptSection $expected): string
    {
        if (null === $expected) {
            return '';
        }

        if ($window->parts->has(OutputPartKind::Newline)) {
            return '';
        }

        if (!$window->parts->has(OutputPartKind::ExceptionMessage)) {
            return '';
        }

        if (!$this->hasFollowingInlineOutput($code->contents, $window)) {
            return '';
        }

        return '|expected:' . mb_strtolower($expected->name) . ':following-inline-output';
    }

    public function expectationKey(OutputExpectation $expectation): string
    {
        $key = '';

        foreach ($expectation->values() as $kind => $value) {
            if ('' === $value) {
                $key .= '|expected:' . str_replace('_', '-', $kind) . ':empty';
            }
        }

        return $key;
    }

    private function hasFollowingInlineOutput(string $code, Window $window): bool
    {
        $parsed = $this->ast->parse($code);

        if (null === $parsed) {
            return false;
        }

        return $this->statementsHaveFollowingInlineOutput($parsed->statements, $window->endOffset, $parsed->offsetDelta);
    }

    /** @param list<Stmt> $statements */
    private function statementsHaveFollowingInlineOutput(array $statements, int $windowEnd, int $offsetDelta): bool
    {
        foreach ($statements as $statement) {
            $start = $this->ast->filePosition($statement, 'startFilePos', $offsetDelta);

            if ($statement instanceof Stmt\InlineHTML && null !== $start && $start >= $windowEnd && '' !== mb_trim($statement->value)) {
                return true;
            }

            if ($this->statementsHaveFollowingInlineOutput($this->ast->childStatements($statement), $windowEnd, $offsetDelta)) {
                return true;
            }
        }

        return false;
    }
}
