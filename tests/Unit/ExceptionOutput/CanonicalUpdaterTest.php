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

    public function testUpdatesLowercaseCaughtLabel(): void
    {
        $update = new CanonicalUpdater()->update(
            'EXPECT',
            "caught 1\n",
            "Exception: 1\n",
        );

        self::assertSame("Exception: 1\n", $update->output);
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
}
