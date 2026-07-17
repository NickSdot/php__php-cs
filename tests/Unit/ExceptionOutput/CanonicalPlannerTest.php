<?php

declare(strict_types=1);

namespace Tests\Unit\ExceptionOutput;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing\CanonicalPlanner;
use PHPUnit\Framework\TestCase;

final class CanonicalPlannerTest extends TestCase
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

        $plans = new CanonicalPlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), \\PHP_EOL;", $plans[0]->replacement);
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

        $plans = new CanonicalPlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), \\PHP_EOL;", $plans[0]->replacement);
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

        $plans = new CanonicalPlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), \\PHP_EOL;", $plans[0]->replacement);
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

        $plans = new CanonicalPlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), ' on line ', \$e->getLine(), \\PHP_EOL;", $plans[0]->replacement);
    }

    public function testPlansLinePrefixRewriteAsCanonicalLocationOutput(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new Exception('x');
            } catch (Exception $e) {
                echo $e->getLine() . ": " . $e->getMessage() . "\n";
            }
            PHP;

        $plans = new CanonicalPlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), ' on line ', \$e->getLine(), \\PHP_EOL;", $plans[0]->replacement);
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

        $plans = new CanonicalPlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), \\PHP_EOL;", $plans[0]->replacement);
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

        $plans = new CanonicalPlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo 'class-based reflection: ', \$e::class, ': ', \$e->getMessage(), \\PHP_EOL;", $plans[0]->replacement);
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

        $plans = new CanonicalPlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo 'invalid flags: ', \$e::class, ': ', \$e->getMessage(), \\PHP_EOL;", $plans[0]->replacement);
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

        $plans = new CanonicalPlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), \\PHP_EOL;", $plans[0]->replacement);
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

        $plans = new CanonicalPlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), \\PHP_EOL;", $plans[0]->replacement);
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

        $plans = new CanonicalPlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), \\PHP_EOL;", $plans[0]->replacement);
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

        $plans = new CanonicalPlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo 'ldap_modify: UNEXPECTED: ', \$e::class, ': ', \$e->getMessage(), \\PHP_EOL;", $plans[0]->replacement);
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

        $plans = new CanonicalPlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo 'Valid flags rejected: ', \$e::class, ': ', \$e->getMessage(), \\PHP_EOL;", $plans[0]->replacement);
    }

    public function testDoesNotPlanFailedAsExpectedDiagnosticPrefixRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new Exception('x');
            } catch (Exception $e) {
                echo "Instance-based creation failed as expected: " . $e->getMessage() . "\n";
            }
            PHP;

        self::assertSame([], new CanonicalPlanner()->plans($code));
    }

    public function testDoesNotPlanCompareExceptionDiagnosticPrefixRewrite(): void
    {
        $code = <<<'PHP_WRAP'
            <?php
            try {
                throw new Exception('x');
            } catch (Exception $e) {
                echo "Compare Exception: " . $e->getMessage() . PHP_EOL;
            }
            PHP_WRAP;

        self::assertSame([], new CanonicalPlanner()->plans($code));
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

        $plans = new CanonicalPlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), \\PHP_EOL;", $plans[0]->replacement);
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

        $plans = new CanonicalPlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$exception::class, ': ', \$exception->getMessage(), \\PHP_EOL;", $plans[0]->replacement);
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

        $plans = new CanonicalPlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), \\PHP_EOL;", $plans[0]->replacement);
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

        $plans = new CanonicalPlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), \\PHP_EOL;", $plans[0]->replacement);
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

        $plans = new CanonicalPlanner()->plans($code);

        self::assertCount(1, $plans);
        self::assertSame("echo \$e::class, ': ', \$e->getMessage(), \\PHP_EOL;", $plans[0]->replacement);
    }

    public function testDoesNotPlanDescriptiveContextRewrite(): void
    {
        $code = <<<'PHP'
            <?php
            try {
                throw new Exception('x');
            } catch (Exception $e) {
                echo "PQ Case $i: " . $e->getMessage() . "\n";
            }
            PHP;

        self::assertSame([], new CanonicalPlanner()->plans($code));
    }
}
