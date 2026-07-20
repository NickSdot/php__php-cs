<?php

declare(strict_types=1);

namespace Tests\Unit\ExceptionOutput;

use InternalsCS\Fixers\ExceptionOutput\Fixing\OutputRewritePlanner;
use InternalsCS\TextEdit;
use PHPUnit\Framework\TestCase;

use function mb_substr;
use function str_replace;
use function usort;

final class OutputRewritePlannerTest extends TestCase
{
    public function testPlansTrashLabelClassMessageRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new Exception('x');
            } catch (Exception $e) {
                echo "Caught in " . $e::class . ": " . $e->getMessage() . "()\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansCaughtExceptionLabelRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new Exception('x');
            } catch (Exception $e) {
                echo "Caught Exception: " . $e->getMessage() . "\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansAssertLabelRewrite(): void
    {
        $code = <<<'PHP_WRAP'
            <?php
            try {
                throw new AssertionError('x');
            } catch (AssertionError $e) {
                echo 'assert(): ', $e->getMessage(), ' failed', PHP_EOL;
            }
            PHP_WRAP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansLocationRewriteWithoutDroppingLine(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new ParseError('x');
            } catch (ParseError $e) {
                echo "Parse error: {$e->getMessage()} on line {$e->getLine()}\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), ' on line ', \$e->getLine(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansLinePrefixRewriteAsNormalisedLocationOutput(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new Exception('x');
            } catch (Exception $e) {
                echo $e->getLine() . ": " . $e->getMessage() . "\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), ' on line ', \$e->getLine(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansParenthesizedLineRewriteAsNormalisedLocationOutput(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new Exception('x');
            } catch (Exception $e) {
                echo $e->getMessage() . "(" . $e->getLine() . ")\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), ' on line ', \$e->getLine(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansAtFileLineRewriteAsNormalisedLocationOutput(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new RuntimeException('x');
            } catch (RuntimeException $e) {
                echo "Caught {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame(
            "echo \$e::class, ': ', \$e->getMessage(), ' in ', \$e->getFile(), ' on line ', \$e->getLine(), PHP_EOL;",
            $plans[0]->replacement,
        );
    }

    public function testPlansCodeMessageHyphenFileLineRewriteAsNormalisedLocationOutput(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new TypeError('x');
            } catch (TypeError $ex) {
                echo "{$ex->getCode()}: {$ex->getMessage()} - {$ex->getFile()}({$ex->getLine()})\n\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame(
            "echo \$ex::class, ': ', \$ex->getCode(), ': ', \$ex->getMessage(), ' in ', \$ex->getFile(), ' on line ', \$ex->getLine(), PHP_EOL;",
            $plans[0]->replacement,
        );
    }

    public function testPlansCodeRewriteAfterMessage(): void
    {
        $code = <<<'PHP_WRAP'
            <?php
            try {
                throw new ValueError('x', 3);
            } catch (ValueError $e) {
                echo get_class($e) . ': ' . $e->getCode() . ', ' . $e->getMessage() . \PHP_EOL;
            }
            PHP_WRAP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getCode(), ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansVarDumpCodeMessageRewriteAfterMessage(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new Error('x', 0);
            } catch (Error $e) {
                var_dump($e->getCode(), $e->getMessage());
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getCode(), ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansMixedVarDumpMessageAndVariableRewrite(): void
    {
        $code = <<<'PHP_WRAP'
            <?php
            try {
                $msg = "Some error \x00 message";
                throw new Exception($msg);
            } catch(Exception $e) {
                var_dump($e->getMessage(), $msg);
            }
            PHP_WRAP;

        $plans = new OutputRewritePlanner()->plans($code);
        $fixed = self::applyPlans($code, $plans);

        self::assertCount(1, $plans);
        self::assertStringContainsString("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;\n    var_dump(\$msg);", $fixed);
    }

    public function testPlansHtmlBreakSuffixRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new Exception('x');
            } catch (Exception $e) {
                echo $e->getMessage() . '<br>';
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansHtmlBreakSuffixRewriteWithFollowingNewlineOutput(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new Exception('x');
            } catch (Exception $e) {
                echo $e->getMessage() . '<br>';
                echo "\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);
        $fixed = self::applyPlans($code, $plans);

        self::assertCount(1, $plans);
        self::assertStringContainsString("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $fixed);
        self::assertStringNotContainsString("echo \"\\n\";", $fixed);
    }

    public function testPlansExpectedExceptionLabelRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new RuntimeException('x');
            } catch (RuntimeException $e) {
                echo 'expected exception: ' . $e->getMessage() . "\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansContextLabelRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new ReflectionException('x');
            } catch (ReflectionException $e) {
                echo "Expected exception for class-based reflection: " . $e->getMessage() . "\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo 'class-based reflection: ', \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansUnexpectedExceptionLabelRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new ReflectionException('x');
            } catch (ReflectionException $e) {
                echo "Unexpected exception: " . $e->getMessage() . "\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo 'unexpected: ', \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansExceptionThrownForContextLabelRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new Exception('x');
            } catch (Exception $e) {
                echo "Exception thrown for invalid flags: " . $e->getMessage() . "\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo 'invalid flags: ', \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansPlainTrashLabelRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new PDOException('x');
            } catch (PDOException $e) {
                echo 'PDOException message: ' . $e->getMessage() . "\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansExceptionTypeThrownLabelRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new InvalidArgumentException('x');
            } catch (InvalidArgumentException $e) {
                echo "InvalidArgumentException thrown: " . $e->getMessage() . "\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansBracketedNumericMarkerPrefixRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new RuntimeException('x');
            } catch (Throwable $e) {
                echo '[001] '.$e->getMessage()."\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo '[001] ', \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansErrorNumberVarDumpMarkerPrefixRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new RuntimeException('x');
            } catch (Throwable $e) {
                var_dump('ERROR 1', $e->getMessage());
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo 'ERROR 1: ', \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansVariableClassMessageMarkerPrefixRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            $type = 'manual_type';

            try {
                throw new RuntimeException('x');
            } catch (Throwable $e) {
                echo $type . "=>" . get_class($e) . ": " . $e->getMessage()."\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$type, '=>', \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansPreservedLiteralMessagePrefixRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new TypeError('x');
            } catch (TypeError $e) {
                echo "bool: ", $e->getMessage(), "\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo 'bool: ', \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansPreservedLiteralClassMessagePrefixRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new TypeError('x');
            } catch (Throwable $t) {
                echo "Wrong exception type thrown: ".get_class($t)." : ".$t->getMessage()."\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo 'Wrong exception type thrown: ', \$t::class, ': ', \$t->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansPreservedVariableMessagePrefixRewrite(): void
    {
        $code = <<<'PHP_WRAP'
            <?php
            foreach (['getMinimum'] as $method) {
                try {
                    throw new Error('x');
                } catch (Error $e) {
                    echo $method, ': ', $e->getMessage(), PHP_EOL;
                }
            }
            PHP_WRAP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$method, ': ', \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansDynamicParenthesizedContextPrefixRewrite(): void
    {
        $code = <<<'PHP_WRAP'
            <?php
            try {
                throw new DOMException('x');
            } catch (DOMException $e) {
                echo "($ns_readable, \"$qname\"): {$e->getMessage()}\n";
            }
            PHP_WRAP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \"(\$ns_readable, \\\"\$qname\\\"): \", \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansBracketedClassRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new TypeError('x');
            } catch (TypeError $e) {
                echo '[' . get_class($e) . '] ' . $e->getMessage() . "\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansSpacedClassMessageSeparatorRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new Exception('x');
            } catch (Exception $e) {
                echo get_class($e) , " : " , $e->getMessage() , "\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansQuotedClassMessageWrapperRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new AssertionError('');
            } catch (AssertionError $e) {
                echo $e::class, ": '", $e->getMessage(), "'\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansCatchTypeLabelRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new TypeError('x');
            } catch (TypeError $e) {
                echo "TypeError: ", $e->getMessage(), "\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansUnexpectedCatchDiagnosticRewriteWithoutDuplicatingCatchType(): void
    {
        $code = <<<'PHP_WRAP'
            <?php
            try {
                throw new ValueError('x');
            } catch (ValueError $e) {
                echo "ldap_modify: UNEXPECTED ValueError: ", $e->getMessage(), PHP_EOL;
            }
            PHP_WRAP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo 'ldap_modify: UNEXPECTED: ', \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansDescriptivePrefixRewriteWithoutDroppingContext(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new Exception('x');
            } catch (Exception $e) {
                echo "Valid flags rejected: " . $e->getMessage() . "\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo 'Valid flags rejected: ', \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansFailedAsExpectedDiagnosticPrefixRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new Exception('x');
            } catch (Exception $e) {
                echo "Instance-based creation failed as expected: " . $e->getMessage() . "\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo 'Instance-based creation failed as expected: ', \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansCompareExceptionDiagnosticPrefixRewrite(): void
    {
        $code = <<<'PHP_WRAP'
            <?php
            try {
                throw new Exception('x');
            } catch (Exception $e) {
                echo "Compare Exception: " . $e->getMessage() . PHP_EOL;
            }
            PHP_WRAP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo 'Compare: ', \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansParenthesizedClassLabelRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new DivisionByZeroError('x');
            } catch (DivisionByZeroError $e) {
                echo "Exception (" . get_class($e) . "): " . $e->getMessage() . "\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansCaughtParenthesizedClassMessageRewrite(): void
    {
        $code = <<<'PHP_WRAP'
            <?php
            try {
                throw new Exception('x');
            } catch (Exception $e) {
                echo 'Caught ' . get_class($e) . '(' . $e->getMessage() . ")\n";
            }
            PHP_WRAP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansCatchInsideClosureArgument(): void
    {
        $code = <<<'PHP'
            <?php
            new Fiber(function () {
                try {
                    Fiber::suspend('test');
                } catch (Exception $exception) {
                    var_dump($exception->getMessage());
                }
            });
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$exception::class, ': ', \$exception->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansClassThenMessageRewriteAsOneStatement(): void
    {
        $code = <<<'PHP_WRAP'
            <?php
            try {
                throw new ArgumentCountError('x');
            } catch (ArgumentCountError $e) {
                echo get_class($e) . PHP_EOL;
                echo $e->getMessage() . PHP_EOL;
            }
            PHP_WRAP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansLeadingSeparatorRewrite(): void
    {
        $code = <<<'PHP_WRAP'
            <?php
            try {
                throw new ValueError('x');
            } catch (ValueError $e) {
                echo \PHP_EOL . $e->getMessage() . \PHP_EOL;
            }
            PHP_WRAP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo PHP_EOL, \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansMovesLeadingSeparatorToPreviousLiteralEcho(): void
    {
        $code = <<<'PHP_WRAP'
            <?php
            echo "Position is => ";
            try {
                throw new TypeError('x');
            } catch (TypeError $e) {
                echo "\n", $e->getMessage(), "\n";
            }
            PHP_WRAP;

        $plans = new OutputRewritePlanner()->plans($code);
        $fixed = self::applyPlans($code, $plans);

        self::assertCount(2, $plans);
        self::assertStringContainsString("echo \"Position is => \", PHP_EOL;", $fixed);
        self::assertStringContainsString("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $fixed);
        self::assertStringNotContainsString("echo PHP_EOL, \$e::class", $fixed);
    }

    public function testDoesNotMoveLeadingSeparatorBeforeTryOutput(): void
    {
        $code = <<<'PHP_WRAP'
            <?php
            echo "Position is => ";
            try {
                var_dump(strpos('test', 't', 'bad'));
            } catch (TypeError $e) {
                echo "\n", $e->getMessage(), "\n";
            }
            PHP_WRAP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo PHP_EOL, \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansMovesLeadingSeparatorToPreviousFunctionEcho(): void
    {
        $code = <<<'PHP_WRAP'
            <?php
            echo bcdiv("20.56", "4");
            try {
                bcscale(-4);
            } catch (\ValueError $e) {
                echo \PHP_EOL . $e->getMessage() . \PHP_EOL;
            }
            PHP_WRAP;

        $plans = new OutputRewritePlanner()->plans($code);
        $fixed = self::applyPlans($code, $plans);

        self::assertCount(2, $plans);
        self::assertStringContainsString("echo bcdiv(\"20.56\", \"4\"), PHP_EOL;", $fixed);
        self::assertStringContainsString("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $fixed);
        self::assertStringNotContainsString("echo PHP_EOL, \$e::class", $fixed);
    }

    public function testPlansMessageBeforeTraceWithoutAddingBlankLine(): void
    {
        $code = <<<'PHP_WRAP'
            <?php
            try {
                throw new Exception("x\n");
            } catch (Exception $e) {
                print $e->getMessage();
                print_r($e->getTrace());
            }
            PHP_WRAP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage();", $plans[0]->replacement);
    }

    public function testPlansTrailingCatchMessageBeforeLeadingNewlineSection(): void
    {
        $code = <<<'PHP_WRAP'
            <?php
            try {
                throw new ArgumentCountError('x');
            } catch (ArgumentCountError $e) {
                print('Error found: '.$e->getMessage());
            }

            echo "\n*** Next section ***\n";
            PHP_WRAP;

        $plans = new OutputRewritePlanner()->plans($code);
        $fixed = self::applyPlans($code, $plans);

        self::assertCount(2, $plans);
        self::assertStringContainsString("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $fixed);
        self::assertStringContainsString("echo \"*** Next section ***\\n\";", $fixed);
    }

    public function testPlansPlainTrailingCatchMessageBeforeLeadingNewlineSection(): void
    {
        $code = <<<'PHP_WRAP'
            <?php
            try {
                throw new ValueError('x');
            } catch (ValueError $e) {
                echo $e->getMessage();
            }

            echo "\n\n*** Next section ***\n";
            PHP_WRAP;

        $plans = new OutputRewritePlanner()->plans($code);
        $fixed = self::applyPlans($code, $plans);

        self::assertCount(2, $plans);
        self::assertStringContainsString("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $fixed);
        self::assertStringContainsString("echo \"\\n*** Next section ***\\n\";", $fixed);
    }

    public function testPlansTrailingCatchMessageBeforeStandalonePhpEolSeparator(): void
    {
        $code = <<<'PHP_WRAP'
            <?php
            try {
                throw new ValueError('x');
            } catch (ValueError $exception) {
                echo $exception->getMessage();
            }

            echo PHP_EOL;

            try {
                throw new ValueError('y');
            } catch (ValueError $exception) {
                echo $exception->getMessage();
            }
            PHP_WRAP;

        $plans = new OutputRewritePlanner()->plans($code);
        $fixed = self::applyPlans($code, $plans);

        self::assertCount(3, $plans);
        self::assertStringContainsString("echo \$exception::class, ': ', \$exception->getMessage(), PHP_EOL;", $fixed);
        self::assertStringNotContainsString('echo PHP_EOL;', $fixed);
    }

    public function testPlansCatchInsideClassMethod(): void
    {
        $code = <<<'PHP'
            <?php
            class Proxy {
                function callOne() {
                    try {
                        throw new Exception('NONE');
                    } catch (Exception $e) {
                        echo 'Caught: '.$e->getMessage()."\n";
                    }
                }
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testDoesNotPlanPreviousExceptionRewrite(): void
    {
        $code = <<<'PHP_WRAP'
            <?php
            try {
                throw new Exception('x');
            } catch (Exception $e) {
                echo $e::class, ': ', $e->getMessage(), PHP_EOL;
                $previous = $e->getPrevious();
                echo '    ', $previous::class, ': ', $previous->getMessage(), PHP_EOL;
            }
            PHP_WRAP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansRewriteAfterUtf8BytesBeforeCatchOutput(): void
    {
        $code = str_replace('{APOSTROPHE}', "\xE2\x80\x99", <<<'PHP'
            <?php
            $message = "Can{APOSTROPHE}t";

            try {
                throw new Exception('x');
            } catch (Exception $e) {
                echo $e->getMessage(), "\n";
            }
            PHP);

        $plans = new OutputRewritePlanner()->plans($code);
        $fixed = self::applyPlans($code, $plans);

        self::assertCount(1, $plans);
        self::assertStringContainsString(
            "echo \$e::class, ': ', \$e->getMessage(), PHP_EOL;",
            $fixed,
        );
        self::assertStringNotContainsString('ho $e->getMessage()', $fixed);
    }

    public function testPlansSameStatementMessageTraceRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new Exception('x');
            } catch (Exception $e) {
                echo "  ", $e->getMessage(), "\n", $e->getTraceAsString();
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame(
            "echo '  ', \$e::class . ': ' . \$e->getMessage(), PHP_EOL, \$e->getTraceAsString();",
            $plans[0]->replacement,
        );
    }

    public function testPlansDescriptiveDynamicContextRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new Exception('x');
            } catch (Exception $e) {
                echo "PQ Case $i: " . $e->getMessage() . "\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \"PQ Case \$i: \", \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansDynamicContextRewriteWithoutExceptionMarker(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new ReflectionException('x');
            } catch (ReflectionException $e) {
                echo "Property $property from class: EXCEPTION - " . $e->getMessage() . "\n\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \"Property \$property from class: \", \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansDynamicContextRewriteWithoutThrownMarker(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new TypeError('x');
            } catch (TypeError $e) {
                echo "Valid assignment $prop2 =& $prop1 threw {$e->getMessage()}\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \"Valid assignment \$prop2 =& \$prop1 \", \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansExpressionContextPrefixRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new Exception('x');
            } catch (Exception $e) {
                echo "{$rf->getName()}: {$e->getMessage()}\n";
            }
            PHP;

        $plans = new OutputRewritePlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$rf->getName(), ': ', \$e::class, ': ', \$e->getMessage(), PHP_EOL;", $plans[0]->replacement);
    }

    /** @param list<TextEdit> $plans */
    private static function applyPlans(string $code, array $plans): string
    {
        usort($plans, fn(TextEdit $a, TextEdit $b): int => $b->startOffset <=> $a->startOffset);

        foreach ($plans as $plan) {
            $code = mb_substr($code, 0, $plan->startOffset, '8bit')
                . $plan->replacement
                . mb_substr($code, $plan->endOffset, null, '8bit');
        }

        return $code;
    }
}
