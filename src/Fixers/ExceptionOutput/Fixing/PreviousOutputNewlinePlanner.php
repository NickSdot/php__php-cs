<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Fixing;

use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\TextEdit;
use PhpParser\Node\Stmt;

use function mb_substr;

final readonly class PreviousOutputNewlinePlanner
{
    public function __construct(
        private StatementFactory $statements = new StatementFactory(),
        private OutputStatementBuilder $builder = new OutputStatementBuilder(),
    ) {}

    public function plan(Stmt $statement, string $code, int $offsetDelta): ?TextEdit
    {
        if (!$statement instanceof Stmt\Echo_) {
            return null;
        }

        $output = $this->statements->fromStatement($statement, $code, $offsetDelta);

        if (null === $output || $output->parts->has(OutputPartKind::Newline)) {
            return null;
        }

        $source = mb_substr($code, $output->startOffset, $output->endOffset - $output->startOffset, '8bit');

        if (null === $replacement = $this->builder->appendNewlineToEcho($source)) {
            return null;
        }

        return new TextEdit(
            startOffset: $output->startOffset,
            endOffset: $output->endOffset,
            line: $output->line,
            replacement: $replacement,
        );
    }
}
