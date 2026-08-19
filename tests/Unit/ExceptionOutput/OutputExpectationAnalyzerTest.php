<?php

declare(strict_types=1);

namespace Tests\Unit\ExceptionOutput;

use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputExpectationAnalyzer;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputPart;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputParts;
use PHPUnit\Framework\TestCase;

final class OutputExpectationAnalyzerTest extends TestCase
{
    public function testExtractsValuesByMatchingTheStatementShape(): void
    {
        $output = new OutputParts(
            parts: [
                OutputPart::exceptionClass('e', 'static_class'),
                OutputPart::literal(': \''),
                OutputPart::exceptionMessage('e'),
                OutputPart::literal("'\n"),
            ],
            shape: 'echo',
        );

        $expectation = new OutputExpectationAnalyzer()->analyze(
            $output,
            "unrelated output\nAssertionError: ''\nfollowing output\n",
        );

        self::assertSame('AssertionError', $expectation->value(OutputPartKind::ExceptionClass));
        self::assertSame('', $expectation->value(OutputPartKind::ExceptionMessage));
    }

    public function testDoesNotInferValuesWithoutAStatementAnchor(): void
    {
        $output = new OutputParts(
            parts: [OutputPart::exceptionMessage('e'), OutputPart::literal("\n")],
            shape: 'echo',
        );

        $expectation = new OutputExpectationAnalyzer()->analyze($output, "\n");

        self::assertNull($expectation->value(OutputPartKind::ExceptionMessage));
    }

    public function testDoesNotUseAnExpectedLineWithDifferentLiterals(): void
    {
        $output = new OutputParts(
            parts: [
                OutputPart::exceptionClass('e', 'static_class'),
                OutputPart::literal(': \''),
                OutputPart::exceptionMessage('e'),
                OutputPart::literal("'\n"),
            ],
            shape: 'echo',
        );

        $expectation = new OutputExpectationAnalyzer()->analyze($output, "AssertionError: \"\"\n");

        self::assertNull($expectation->value(OutputPartKind::ExceptionMessage));
    }
}
