<?php

declare(strict_types=1);

namespace Tests\Unit\ExceptionOutput;

use InternalsCS\FixerRunOptions;
use InternalsCS\FixerRunner;
use InternalsCS\Fixers\ExceptionOutput\ExceptionOutputFixer;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

final class NormalizationOnlyRunTest extends TestCase
{
    public function testDefaultRunKeepsNormalizationRewriteCapability(): void
    {
        $path = $this->fixture($this->normalizationOnlyPhpt());

        $result = new FixerRunner(
            rootDir: dirname($path),
            fixerClasses: [ExceptionOutputFixer::class],
        )->run([$path], check: true);

        self::assertSame(1, $result->needsChanges());
    }

    public function testOptInRunSkipsNormalizationOnlyFile(): void
    {
        $path = $this->fixture($this->normalizationOnlyPhpt());

        $result = new FixerRunner(
            rootDir: dirname($path),
            fixerClasses: [ExceptionOutputFixer::class],
            options: new FixerRunOptions(skipNormalizationOnly: true),
        )->run([$path], check: true);

        self::assertSame(0, $result->changed());
    }

    public function testOptInRunSkipsCatchTypeOnlyFile(): void
    {
        $path = $this->fixture(<<<'PHPT'
            --TEST--
            catch-type-only exception assertion
            --FILE--
            <?php
            try {
                throw new TypeError('fixture message');
            } catch (TypeError $e) {
                echo $e::class, ': ', $e->getMessage(), "\n";
            }
            ?>
            --EXPECT--
            TypeError: fixture message
            PHPT);

        $result = new FixerRunner(
            rootDir: dirname($path),
            fixerClasses: [ExceptionOutputFixer::class],
            options: new FixerRunOptions(skipNormalizationOnly: true),
        )->run([$path], check: true);

        self::assertSame(0, $result->changed());
    }

    public function testOptInRunKeepsSubstantiveRewriteAndItsNormalizations(): void
    {
        $path = $this->fixture(<<<'PHPT'
            --TEST--
            substantive exception assertion
            --FILE--
            <?php
            try {
                throw new TypeError('fixture message');
            } catch (TypeError $e) {
                echo $e->getMessage(), PHP_EOL;
            }
            ?>
            --EXPECT--
            fixture message
            PHPT);

        $result = new FixerRunner(
            rootDir: dirname($path),
            fixerClasses: [ExceptionOutputFixer::class],
            options: new FixerRunOptions(skipNormalizationOnly: true),
        )->run([$path], check: true);

        self::assertSame(1, $result->needsChanges());
    }

    private function normalizationOnlyPhpt(): string
    {
        return <<<'PHPT'
            --TEST--
            normalization-only exception assertion
            --FILE--
            <?php
            try {
                throw new TypeError('fixture message');
            } catch (TypeError $e) {
                echo $e::class, ": ", $e->getMessage(), PHP_EOL;
            }
            ?>
            --EXPECT--
            TypeError: fixture message
            PHPT;
    }

    private function fixture(string $contents): string
    {
        $root = sys_get_temp_dir() . '/normalization-only-run-' . bin2hex(random_bytes(6));
        mkdir($root);
        $path = $root . '/fixture.phpt';
        file_put_contents($path, $contents);

        return $path;
    }
}
