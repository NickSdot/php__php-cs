<?php

declare(strict_types=1);

namespace Tests\Unit\ExceptionOutput;

use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\ClassificationSafety;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputFamily;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Generation\Scanner;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

final class ScannerTest extends TestCase
{
    public function testSameTrashyConcatFlavourGetsSameFingerprint(): void
    {
        $root = $this->makeTempDir();
        $first = $this->writePhpt($root, 'a.phpt', 'echo "Caught " . $e->getMessage() . "\n";');
        $second = $this->writePhpt($root, 'b.phpt', 'echo "*** Caught " . $e->getMessage() . "\n";');

        $candidates = new Scanner()->scan([$first, $second], $root);

        self::assertCount(2, $candidates);
        self::assertSame($candidates[0]->key, $candidates[1]->key);
        self::assertSame(OutputFamily::MessageOnly, $candidates[0]->classification->family);
        self::assertSame(ClassificationSafety::Fixable, $candidates[0]->classification->safety);
    }

    public function testConcatAndCommaEchoAreDifferentFlavours(): void
    {
        $root = $this->makeTempDir();
        $concat = $this->writePhpt($root, 'concat.phpt', 'echo "Caught " . $e->getMessage() . "\n";');
        $comma = $this->writePhpt($root, 'comma.phpt', 'echo "Caught ", $e->getMessage(), "\n";');

        $candidates = new Scanner()->scan([$concat, $comma], $root);

        self::assertCount(2, $candidates);
        self::assertNotSame($candidates[0]->key, $candidates[1]->key);
    }

    public function testMixedConcatCommaAndPureCommaEchoAreDifferentFlavours(): void
    {
        $root = $this->makeTempDir();
        $mixed = $this->writePhpt($root, 'mixed.phpt', 'echo $e::class . ": " . $e->getMessage(), "\n";');
        $comma = $this->writePhpt($root, 'comma.phpt', 'echo $e::class, ": ", $e->getMessage(), "\n";');

        $candidates = new Scanner()->scan([$mixed, $comma], $root);

        self::assertCount(2, $candidates);
        self::assertNotSame($candidates[0]->key, $candidates[1]->key);
    }

    public function testVariableNamesDoNotSplitFlavours(): void
    {
        $root = $this->makeTempDir();
        $first = $this->writePhptWithCatchVariable($root, 'first.phpt', 'e', 'echo "Exception: ", $e->getMessage(), "\n";');
        $second = $this->writePhptWithCatchVariable($root, 'second.phpt', 'ex', 'echo "Exception: ", $ex->getMessage(), "\n";');

        $candidates = new Scanner()->scan([$first, $second], $root);

        self::assertCount(2, $candidates);
        self::assertSame($candidates[0]->key, $candidates[1]->key);
    }

    public function testSeparatorWhitespaceSplitsClassMessageFlavours(): void
    {
        $root = $this->makeTempDir();
        $canonical = $this->writePhpt($root, 'canonical.phpt', 'echo get_class($e), \': \', $e->getMessage(), "\n";');
        $spaced = $this->writePhpt($root, 'spaced.phpt', 'echo get_class($e), " : ", $e->getMessage(), "\n";');

        $candidates = new Scanner()->scan([$canonical, $spaced], $root);

        self::assertCount(2, $candidates);
        self::assertNotSame($candidates[0]->key, $candidates[1]->key);
    }

    public function testDescriptiveContextIsRejected(): void
    {
        $root = $this->makeTempDir();
        $source = $this->writePhpt($root, 'case.phpt', 'echo "PQ Case $i: " . $e->getMessage() . "\n";');

        $candidates = new Scanner()->scan([$source], $root);

        self::assertCount(1, $candidates);
        self::assertSame(ClassificationSafety::DescriptiveContext, $candidates[0]->classification->safety);
    }

    public function testCaughtExceptionAndAssertLabelsAreTrash(): void
    {
        $root = $this->makeTempDir();
        $caught = $this->writePhpt($root, 'caught.phpt', 'echo "Caught Exception: " . $e->getMessage() . "\n";');
        $assert = $this->writePhpt($root, 'assert.phpt', "echo 'assert(): ', \$e->getMessage(), ' failed', PHP_EOL;");

        $candidates = new Scanner()->scan([$caught, $assert], $root);

        self::assertCount(2, $candidates);
        self::assertSame(ClassificationSafety::Fixable, $candidates[0]->classification->safety);
        self::assertSame(ClassificationSafety::Fixable, $candidates[1]->classification->safety);
    }

    public function testPlainTrashLabelsAreFixable(): void
    {
        $root = $this->makeTempDir();
        $safely = $this->writePhpt($root, 'safely.phpt', 'echo "Safely caught " . $e->getMessage() . "\n";');
        $pdo = $this->writePhpt($root, 'pdo.phpt', 'echo "PDOException message: " . $e->getMessage() . "\n";');
        $inside = $this->writePhpt($root, 'inside.phpt', 'print "in catch: " . $e->getMessage() . "\n";');
        $ok = $this->writePhpt($root, 'ok.phpt', 'echo "OK! {$e->getMessage()}";');

        $candidates = new Scanner()->scan([$safely, $pdo, $inside, $ok], $root);

        self::assertCount(4, $candidates);

        foreach ($candidates as $candidate) {
            self::assertSame(ClassificationSafety::Fixable, $candidate->classification->safety);
        }
    }

    public function testTrashLabelsKeepDistinctFingerprints(): void
    {
        $root = $this->makeTempDir();
        $generic = $this->writePhpt($root, 'generic.phpt', 'echo "Exception: " . $e->getMessage() . "\n";');
        $error = $this->writePhpt($root, 'error.phpt', 'echo "[Error] " . $e->getMessage() . "\n";');
        $ok = $this->writePhpt($root, 'ok.phpt', 'echo "Ok - " . $e->getMessage() . PHP_EOL;');
        $test = $this->writePhpt($root, 'test.phpt', 'echo "TEST:" . $e->getMessage() . PHP_EOL;');

        $candidates = new Scanner()->scan([$generic, $error, $ok, $test], $root);

        self::assertCount(4, $candidates);
        self::assertSame(ClassificationSafety::Fixable, $candidates[0]->classification->safety);
        self::assertSame(ClassificationSafety::Fixable, $candidates[1]->classification->safety);
        self::assertSame(ClassificationSafety::Fixable, $candidates[2]->classification->safety);
        self::assertSame(ClassificationSafety::Fixable, $candidates[3]->classification->safety);

        self::assertNotSame($candidates[0]->key, $candidates[1]->key);
        self::assertNotSame($candidates[0]->key, $candidates[2]->key);
        self::assertNotSame($candidates[0]->key, $candidates[3]->key);
    }

    public function testMarkerPrefixesAreFixable(): void
    {
        $root = $this->makeTempDir();
        $bracketed = $this->writePhpt($root, 'bracketed.phpt', 'echo "[001] " . $e->getMessage() . "\n";');
        $error = $this->writePhpt($root, 'error.phpt', "var_dump('ERROR 3', \$e->getMessage());");
        $variable = $this->writePhpt($root, 'variable.phpt', 'echo $type . "=>" . get_class($e) . ": " . $e->getMessage()."\n";');

        $candidates = new Scanner()->scan([$bracketed, $error, $variable], $root);

        self::assertCount(3, $candidates);

        foreach ($candidates as $candidate) {
            self::assertSame(ClassificationSafety::Fixable, $candidate->classification->safety);
        }
    }

    public function testMarkerPrefixNumbersDoNotSplitFlavours(): void
    {
        $root = $this->makeTempDir();
        $firstBracketed = $this->writePhpt($root, 'bracketed-first.phpt', 'echo "[001] " . $e->getMessage() . "\n";');
        $secondBracketed = $this->writePhpt($root, 'bracketed-second.phpt', 'echo "[012] " . $e->getMessage() . "\n";');
        $firstError = $this->writePhpt($root, 'error-first.phpt', "var_dump('ERROR 1', \$e->getMessage());");
        $secondError = $this->writePhpt($root, 'error-second.phpt', "var_dump('ERROR 3', \$e->getMessage());");

        $candidates = new Scanner()->scan([$firstBracketed, $secondBracketed, $firstError, $secondError], $root);

        self::assertCount(4, $candidates);
        self::assertSame($candidates[0]->key, $candidates[1]->key);
        self::assertSame($candidates[2]->key, $candidates[3]->key);
    }

    public function testIgnoresMessageCallsOutsideCatchBlocks(): void
    {
        $root = $this->makeTempDir();
        $source = $this->writeRawPhpt($root, 'outside.phpt', <<<'PHP'
            <?php
            $r = new RuntimeException('outside');

            try {
                throw new RuntimeException('inside');
            } catch (Throwable $e) {
                echo $e->getMessage() . "\n";
            }

            var_dump($r->getMessage());
            PHP);

        $candidates = new Scanner()->scan([$source], $root);

        self::assertCount(1, $candidates);
        self::assertSame('echo $e->getMessage() . "\n";', $candidates[0]->statement);
    }

    public function testAdjacentClassThenMessageStatementsAreOneFlavour(): void
    {
        $root = $this->makeTempDir();
        $adjacent = $this->writeRawPhpt($root, 'adjacent.phpt', <<<'PHP'
            <?php
            try {
                throw new UnexpectedValueException('broken');
            } catch (Throwable $e) {
                var_dump(get_class($e));
                echo $e->getMessage() . "\n";
            }
            PHP);
        $single = $this->writePhpt($root, 'single.phpt', 'echo $e->getMessage() . "\n";');

        $candidates = new Scanner()->scan([$adjacent, $single], $root);

        self::assertCount(2, $candidates);
        self::assertSame(
            "var_dump(get_class(\$e));\n    echo \$e->getMessage() . \"\\n\";",
            $candidates[0]->statement,
        );
        self::assertNotSame($candidates[0]->key, $candidates[1]->key);
        self::assertSame(OutputFamily::ClassMessage, $candidates[0]->classification->family);
    }

    public function testReportsFullStatementAfterUtf8BytesBeforeCatchOutput(): void
    {
        $root = $this->makeTempDir();
        $apostrophe = "\xE2\x80\x99";
        $source = $this->writeRawPhpt($root, 'utf8.phpt', <<<PHP
            <?php
            echo "Can{$apostrophe}t";

            try {
                throw new RuntimeException('inside');
            } catch (Throwable \$e) {
                echo "  ", \$e->getMessage(), "\\n", \$e->getTraceAsString();
            }
            PHP);

        $candidates = new Scanner()->scan([$source], $root);

        self::assertCount(1, $candidates);
        self::assertSame('echo "  ", $e->getMessage(), "\n", $e->getTraceAsString();', $candidates[0]->statement);
    }

    private function writePhpt(string $root, string $name, string $statement): string
    {
        $path = $root . '/' . $name;
        $contents = <<<PHPT
            --TEST--
            $name
            --FILE--
            <?php
            try {
                throw new RuntimeException('broken');
            } catch (Throwable \$e) {
                $statement
            }
            --EXPECT--

            PHPT;

        file_put_contents($path, $contents);

        return $path;
    }

    private function writePhptWithCatchVariable(string $root, string $name, string $variable, string $statement): string
    {
        $path = $root . '/' . $name;
        $contents = <<<PHPT
            --TEST--
            $name
            --FILE--
            <?php
            try {
                throw new RuntimeException('broken');
            } catch (Throwable \$$variable) {
                $statement
            }
            --EXPECT--

            PHPT;

        file_put_contents($path, $contents);

        return $path;
    }

    private function writeRawPhpt(string $root, string $name, string $code): string
    {
        $path = $root . '/' . $name;
        $contents = <<<PHPT
            --TEST--
            $name
            --FILE--
            $code
            --EXPECT--

            PHPT;

        file_put_contents($path, $contents);

        return $path;
    }

    private function makeTempDir(): string
    {
        $root = sys_get_temp_dir() . '/scanner-' . bin2hex(random_bytes(6));
        mkdir($root);

        return $root;
    }
}
