<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Fixing;

use InternalsCS\PhpAst;
use InternalsCS\TextEdit;
use PhpParser\Node\Stmt;

use function count;
use function mb_substr;
use function preg_match;

final readonly class CatchTypeRewritePlanner
{
    private const string REPLACEMENT = '\\Throwable';

    public function __construct(
        private PhpAst $ast = new PhpAst(),
    ) {}

    public function plan(Stmt\Catch_ $catch, RewriteScope $scope): ?TextEdit
    {
        $first = $catch->types[0] ?? null;
        $last = $catch->types[count($catch->types) - 1] ?? null;

        if (null === $first || null === $last) {
            return null;
        }

        $start = $this->ast->filePosition($first, 'startFilePos', $scope->offsetDelta);
        $end = $this->ast->filePosition($last, 'endFilePos', $scope->offsetDelta);

        if (null === $start || null === $end || $start < 0 || $end < $start) {
            return null;
        }

        $current = mb_substr($scope->code, $start, $end - $start + 1, '8bit');

        if (self::REPLACEMENT === $current || $this->containsComment($current)) {
            return null;
        }

        return new TextEdit(
            startOffset: $start,
            endOffset: $end + 1,
            line: $first->getStartLine(),
            replacement: self::REPLACEMENT,
        );
    }

    private function containsComment(string $types): bool
    {
        return 1 === preg_match('~/\\*|//|#~', $types);
    }
}
