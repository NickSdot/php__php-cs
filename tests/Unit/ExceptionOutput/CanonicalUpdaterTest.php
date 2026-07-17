<?php

declare(strict_types=1);

namespace Tests\Unit\ExceptionOutput;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalUpdater;
use PHPUnit\Framework\TestCase;

final class CanonicalUpdaterTest extends TestCase
{
    public function testUpdatesBracketedClassOutput(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECT',
            "[TypeError] message\n",
            "TypeError: message\n",
        );

        self::assertSame("TypeError: message\n", $update->output);
    }

    public function testUpdatesIndentedTrashExceptionPrefix(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECT',
            "  Exception: Cannot re-assign \$this\n",
            "Error: Cannot re-assign \$this\n",
        );

        self::assertSame("Error: Cannot re-assign \$this\n", $update->output);
    }

    public function testUpdatesLinePrefixToCanonicalLineSuffix(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECT',
            "34: Objects returned must be traversable\n",
            "Exception: Objects returned must be traversable on line 34\n",
        );

        self::assertSame("Exception: Objects returned must be traversable on line 34\n", $update->output);
    }

    public function testUpdatesExpectfWithoutCollapsingUnchangedPatterns(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECTF',
            "object(stdClass)#%d (0) {\n}\nAttempt to modify property \"abc\" on array\n",
            "object(stdClass)#123 (0) {\n}\nError: Attempt to modify property \"abc\" on array\n",
        );

        self::assertSame(
            "object(stdClass)#%d (0) {\n}\nError: Attempt to modify property \"abc\" on array\n",
            $update->output,
        );
    }

    public function testUpdatesExpectfWithoutCollapsingPathAndLinePlaceholders(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECTF',
            "foo(): Return value must be of type stdClass, array returned in %s on line %d\n",
            "TypeError: foo(): Return value must be of type stdClass, array returned in /tmp/example.phpt on line 6\n",
        );

        self::assertSame(
            "TypeError: foo(): Return value must be of type stdClass, array returned in %s on line %d\n",
            $update->output,
        );
    }

    public function testUpdatesVarDumpStringMessageOutput(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECT',
            "string(4) \"test\"\n",
            "Exception: test\n",
        );

        self::assertSame("Exception: test\n", $update->output);
    }

    public function testUpdatesExpectedExceptionLabel(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECT',
            "expected exception: Iterators are frozen\n",
            "RuntimeException: Iterators are frozen\n",
        );

        self::assertSame("RuntimeException: Iterators are frozen\n", $update->output);
    }

    public function testUpdatesContextLabel(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECT',
            "Expected exception for class-based reflection: Property TestClass::\$dynamic does not exist\n",
            "class-based reflection: ReflectionException: Property TestClass::\$dynamic does not exist\n",
        );

        self::assertSame(
            "class-based reflection: ReflectionException: Property TestClass::\$dynamic does not exist\n",
            $update->output,
        );
    }

    public function testUpdatesExceptionThrownForContextLabel(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECT',
            "Exception thrown for invalid flags: Invalid serialization data for SplPriorityQueue object\n",
            "invalid flags: Exception: Invalid serialization data for SplPriorityQueue object\n",
        );

        self::assertSame(
            "invalid flags: Exception: Invalid serialization data for SplPriorityQueue object\n",
            $update->output,
        );
    }

    public function testUpdatesLowercaseCaughtLabel(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECT',
            "caught 1\n",
            "Exception: 1\n",
        );

        self::assertSame("Exception: 1\n", $update->output);
    }

    public function testUpdatesPlainTrashLabel(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECT',
            "PDOException message: SQLSTATE[HY000]: General error\n",
            "PDOException: SQLSTATE[HY000]: General error\n",
        );

        self::assertSame("PDOException: SQLSTATE[HY000]: General error\n", $update->output);
    }

    public function testUpdatesParenthesizedClassLabel(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECT',
            "Exception (DivisionByZeroError): Modulo by zero\n",
            "DivisionByZeroError: Modulo by zero\n",
        );

        self::assertSame("DivisionByZeroError: Modulo by zero\n", $update->output);
    }

    public function testUpdatesClassLabelWithSpacedColon(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECT',
            "Exception : Signal\n",
            "Exception: Signal\n",
        );

        self::assertSame("Exception: Signal\n", $update->output);
    }

    public function testUpdatesCaughtParenthesizedClassMessage(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECT',
            "Caught Exception(Hello)\n",
            "Exception: Hello\n",
        );

        self::assertSame("Exception: Hello\n", $update->output);
    }

    public function testUpdatesPreservedDescriptivePrefix(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECT',
            "Valid flags rejected: invalid\n",
            "Valid flags rejected: Exception: invalid\n",
        );

        self::assertSame("Valid flags rejected: Exception: invalid\n", $update->output);
    }

    public function testUpdatesTestLabel(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECTF',
            "TEST:Constructor failed\n",
            "Exception: Constructor failed\n",
        );

        self::assertSame("Exception: Constructor failed\n", $update->output);
    }

    public function testUpdatesErrorFoundLabel(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECT',
            "Error found: Argument number specifier must be greater than zero and less than 2147483647\n",
            "ValueError: Argument number specifier must be greater than zero and less than 2147483647\n",
        );

        self::assertSame(
            "ValueError: Argument number specifier must be greater than zero and less than 2147483647\n",
            $update->output,
        );
    }

    public function testUpdatesErrorFoundLabelAfterStableOutputPrefix(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECT',
            "printf test 30:Error found: Argument number specifier must be greater than zero and less than 2147483647\n",
            "printf test 30:ValueError: Argument number specifier must be greater than zero and less than 2147483647\n",
        );

        self::assertSame(
            "printf test 30:ValueError: Argument number specifier must be greater than zero and less than 2147483647\n",
            $update->output,
        );
    }

    public function testUpdatesLeadingBlankMessageOutput(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECT',
            "\nmessage\n",
            "Exception: message\n",
        );

        self::assertSame("Exception: message\n", $update->output);
    }

    public function testUpdatesDroppedInternalBlankMessageOutput(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECT',
            "First:\nmessage\n\nSecond:\nmessage\n",
            "First:\nException: message\nSecond:\nException: message\n",
        );

        self::assertSame("First:\nException: message\nSecond:\nException: message\n", $update->output);
    }

    public function testUpdatesHtmlBreakSuffix(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECT',
            "message<br>\n",
            "Exception: message\n",
        );

        self::assertSame("Exception: message\n", $update->output);
    }

    public function testUpdatesParenthesizedLineSuffix(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECT',
            "message(6)\n",
            "TypeError: message on line 6\n",
        );

        self::assertSame("TypeError: message on line 6\n", $update->output);
    }

    public function testUpdatesExpectfAtFileLineLocation(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECTF',
            "Caught In sleep at %sphar_metadata_write4.php:12\n",
            "RuntimeException: In sleep in /tmp/phar_metadata_write4.php on line 12\n",
        );

        self::assertSame(
            "RuntimeException: In sleep in %sphar_metadata_write4.php on line 12\n",
            $update->output,
        );
    }

    public function testUpdatesSoapFaultCatchTypeLabel(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECT',
            "fixture message\n",
            "SoapFault: fixture message\n",
        );

        self::assertSame("SoapFault: fixture message\n", $update->output);
    }
}
