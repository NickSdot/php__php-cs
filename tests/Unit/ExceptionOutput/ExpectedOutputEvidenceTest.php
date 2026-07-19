<?php

declare(strict_types=1);

namespace Tests\Unit\ExceptionOutput;

use InternalsCS\Fixers\ExceptionOutput\Analysis\ExpectedOutputEvidence;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputPart;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputParts;
use PHPUnit\Framework\TestCase;

final class ExpectedOutputEvidenceTest extends TestCase
{
    public function testDumpedStringRepresentsVarDumpedExceptionMessage(): void
    {
        $parts = new OutputParts(
            parts: [OutputPart::exceptionMessage('e')],
            shape: 'var_dump',
        );

        self::assertTrue(new ExpectedOutputEvidence()->contains("string(5) \"Damn!\"\n====DONE====\n", $parts));
    }
}
