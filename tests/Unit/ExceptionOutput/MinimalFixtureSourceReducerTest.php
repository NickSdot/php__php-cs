<?php

declare(strict_types=1);

namespace Tests\Unit\ExceptionOutput;

use InternalsCS\Fixers\ExceptionOutput\Generation\CandidateCollector;
use InternalsCS\Fixers\ExceptionOutput\Generation\MinimalFixtureSourceReducer;
use InternalsCS\Fixture\FixtureCaseName;
use InternalsCS\Fixture\FixtureRewriteRunner;
use InternalsCS\Fixture\FixtureSource;
use InternalsCS\SourceFile;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

final class MinimalFixtureSourceReducerTest extends TestCase
{
    public function testReducesSourceFileToSingleFlavourFixture(): void
    {
        $root = sys_get_temp_dir() . '/exception-output-reducer-' . bin2hex(random_bytes(6));
        mkdir($root);

        $sourcePath = $root . '/source.phpt';
        file_put_contents($sourcePath, <<<'PHPT'
            --TEST--
            Large source file name should not be preserved
            --FILE--
            <?php
            try {
                throw new RuntimeException('broken');
            } catch (Throwable $e) {
                echo "Caught: " . $e->getMessage() . "\n";
            }
            --EXPECT--
            Caught: broken

            PHPT);

        $collector = new CandidateCollector();
        $candidates = $collector->collect(new SourceFile($sourcePath, $root));
        self::assertCount(1, $candidates);

        $candidate = $candidates[0] ?? throw new \LogicException('Expected one candidate');
        $source = new FixtureSource([$candidate]);
        $fixture = new MinimalFixtureSourceReducer()->reduce($source, new ChangedFixtureRewriteRunner());

        self::assertNotNull($fixture);
        self::assertStringContainsString(
            "--TEST--\nException output: " . new FixtureCaseName()->fromFixtureSource($source) . "\n",
            $fixture,
        );
        self::assertStringContainsString("throw new \\RuntimeException('fixture message');", $fixture);
        self::assertStringNotContainsString('Large source file name should not be preserved', $fixture);

        $fixturePath = $root . '/old.phpt';
        file_put_contents($fixturePath, $fixture);

        $fixtureCandidates = $collector->collect(new SourceFile($fixturePath, $root));
        self::assertCount(1, $fixtureCandidates);
        $fixtureCandidate = $fixtureCandidates[0] ?? throw new \LogicException('Expected one fixture candidate');
        self::assertSame($candidate->fixtureKey, $fixtureCandidate->fixtureKey);
    }

    public function testPreservesCatchTypeWhenReducingSourceFile(): void
    {
        $root = sys_get_temp_dir() . '/exception-output-reducer-' . bin2hex(random_bytes(6));
        mkdir($root);

        $sourcePath = $root . '/source.phpt';
        file_put_contents($sourcePath, <<<'PHPT'
            --TEST--
            Catch type should be preserved
            --FILE--
            <?php
            try {
                throw new ValueError('broken');
            } catch (ValueError $e) {
                echo "ldap_modify: UNEXPECTED ValueError: ", $e->getMessage(), PHP_EOL;
            }
            --EXPECT--
            ldap_modify: UNEXPECTED ValueError: broken

            PHPT);

        $candidates = new CandidateCollector()->collect(new SourceFile($sourcePath, $root));
        $candidate = $candidates[0] ?? throw new \LogicException('Expected one candidate');

        $fixture = new MinimalFixtureSourceReducer()->reduce(new FixtureSource([$candidate]), new ChangedFixtureRewriteRunner());

        self::assertNotNull($fixture);
        self::assertStringContainsString("throw new \\ValueError('fixture message');", $fixture);
        self::assertStringContainsString('} catch (\\ValueError $e) {', $fixture);
    }

    public function testStubsUnavailableCatchTypeWhenReducingSourceFile(): void
    {
        $root = sys_get_temp_dir() . '/exception-output-reducer-' . bin2hex(random_bytes(6));
        mkdir($root);

        $sourcePath = $root . '/source.phpt';
        file_put_contents($sourcePath, <<<'PHPT'
            --TEST--
            Missing extension exception class should be stubbed
            --FILE--
            <?php
            try {
                throw new RuntimeException('broken');
            } catch (FixtureMissingExtensionException $e) {
                echo "extension exception: ", $e->getMessage(), "\n";
            }
            --EXPECT--
            extension exception: broken

            PHPT);

        $candidates = new CandidateCollector()->collect(new SourceFile($sourcePath, $root));
        $candidate = $candidates[0] ?? throw new \LogicException('Expected one candidate');
        $fixture = new MinimalFixtureSourceReducer()->reduce(new FixtureSource([$candidate]), new ChangedFixtureRewriteRunner());

        self::assertNotNull($fixture);
        self::assertStringContainsString("class FixtureMissingExtensionException extends \\RuntimeException", $fixture);
        self::assertStringContainsString("throw new \\FixtureMissingExtensionException('fixture message');", $fixture);
        self::assertStringContainsString('} catch (\\FixtureMissingExtensionException $e) {', $fixture);
    }

    public function testNormalisesSyntheticLineNumbersInExpectfOutput(): void
    {
        $root = sys_get_temp_dir() . '/exception-output-reducer-' . bin2hex(random_bytes(6));
        mkdir($root);

        $cases = [
            ['echo $e->getLine() . ": " . $e->getMessage() ."\\n";', "%d: broken\n", "%d: fixture message\n"],
            ['echo $e->getMessage() . "(" . $e->getLine() .  ")\\n";', "broken(%d)\n", "fixture message(%d)\n"],
        ];

        foreach ($cases as $index => [$statement, $sourceExpected, $fixtureExpected]) {
            $sourcePath = $root . '/source-' . $index . '.phpt';
            file_put_contents($sourcePath, "--TEST--\nLine number output\n--FILE--\n<?php\ntry {\n    throw new RuntimeException('broken');\n} catch (Throwable \$e) {\n    $statement\n}\n--EXPECTF--\n$sourceExpected");

            $candidates = new CandidateCollector()->collect(new SourceFile($sourcePath, $root));
            $candidate = $candidates[0] ?? throw new \LogicException('Expected one candidate');
            $fixture = new MinimalFixtureSourceReducer()->reduce(new FixtureSource([$candidate]), new ChangedFixtureRewriteRunner());

            self::assertNotNull($fixture);
            self::assertStringContainsString("--EXPECTF--\n$fixtureExpected", $fixture);
        }
    }

    public function testPreservesMovableLeadingSeparatorContext(): void
    {
        $fixture = $this->reduceContextualSource(<<<'PHP'
            echo 'preceding output';
            try {
                throw new ValueError('broken');
            } catch (ValueError $e) {
                echo "\n", $e->getMessage(), "\n";
            }
            PHP, "preceding output\nbroken\n");

        self::assertStringContainsString("echo 'preceding output';\ntry {", $fixture);
        self::assertStringContainsString("    throw new \\ValueError('fixture message');", $fixture);
        self::assertStringNotContainsString('var_dump(throw', $fixture);
    }

    public function testPreservesTryOutputThatBlocksLeadingSeparatorMove(): void
    {
        $fixture = $this->reduceContextualSource(<<<'PHP'
            echo 'preceding output';
            try {
                var_dump(strpos('test', 't', []));
            } catch (TypeError $e) {
                echo "\n", $e->getMessage(), "\n";
            }
            PHP, "preceding output\nbroken\n");

        self::assertStringContainsString("echo 'preceding output';\ntry {", $fixture);
        self::assertStringContainsString("    var_dump(throw new \\TypeError('fixture message'));", $fixture);
    }

    public function testPreservesExpectedEmptyQuotedMessage(): void
    {
        $fixture = $this->reduceContextualSource(<<<'PHP'
            try {
                throw new AssertionError('');
            } catch (AssertionError $e) {
                echo $e::class, ": '", $e->getMessage(), "'\n";
            }
            PHP, "AssertionError: ''\n");

        self::assertStringContainsString("throw new \\AssertionError('');", $fixture);
        self::assertStringContainsString("--EXPECTF--\nAssertionError: ''\n", $fixture);
    }

    public function testPreservesFollowingTraceThatControlsMessageNewline(): void
    {
        $fixture = $this->reduceContextualSource(<<<'PHP'
            try {
                throw new RuntimeException('broken');
            } catch (RuntimeException $e) {
                echo $e->getMessage();
                print_r($e->getTrace());
            }
            PHP, "brokenArray\n(\n)\n");

        self::assertStringContainsString("    echo \$e->getMessage();\n    print_r(\$e->getTrace());", $fixture);
    }

    public function testPreservesRelativeIndentationBetweenAdjacentStatements(): void
    {
        $fixture = $this->reduceContextualSource(<<<'PHP'
            try {
                throw new RuntimeException('broken');
            } catch (RuntimeException $e) {
                var_dump(get_class($e));
                echo $e->getMessage(), "\n";
            }
            PHP, "string(16) \"RuntimeException\"\nbroken\n");

        self::assertStringContainsString(
            "    var_dump(get_class(\$e));\n        echo \$e->getMessage(), \"\\n\";",
            $fixture,
        );
    }

    private function reduceContextualSource(string $code, string $expected): string
    {
        $root = sys_get_temp_dir() . '/exception-output-reducer-' . bin2hex(random_bytes(6));
        mkdir($root);

        $sourcePath = $root . '/source.phpt';
        file_put_contents($sourcePath, "--TEST--\nContextual source\n--FILE--\n<?php\n$code\n--EXPECT--\n$expected");

        $candidates = new CandidateCollector()->collect(new SourceFile($sourcePath, $root));
        $candidate = $candidates[0] ?? throw new \LogicException('Expected one candidate');
        $fixture = new MinimalFixtureSourceReducer()->reduce(new FixtureSource([$candidate]), new ChangedFixtureRewriteRunner());

        return $fixture ?? throw new \LogicException('Expected a reduced fixture');
    }
}

final readonly class ChangedFixtureRewriteRunner implements FixtureRewriteRunner
{
    public function printFile(string $path): array
    {
        return [
            'changed' => true,
            'failed' => false,
            'output' => (string) file_get_contents($path) . "\n",
            'failure' => null,
        ];
    }
}
