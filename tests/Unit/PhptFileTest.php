<?php

declare(strict_types=1);

namespace Tests\Unit;

use InternalsCS\PhpSrcTestStyle\PhptFile;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;

final class PhptFileTest extends TestCase
{
    public function testReadsAndUpdatesSectionsWithoutLosingHeaders(): void
    {
        $path = $this->writeTempPhpt(<<<'PHPT'
            --TEST--
            sample
            --FILE--
            <?php
            echo "old\n";
            --EXPECT--
            old
            PHPT);

        $file = new PhptFile($path, dirname($path));

        self::assertSame('FILE', $file->codeSectionName());
        self::assertSame('EXPECT', $file->expectedSectionName());
        $fileSection = $file->getSection('FILE');
        self::assertNotNull($fileSection);
        self::assertStringContainsString('echo "old\n";', $fileSection);

        $file->setSection('FILE', "<?php\necho \"new\\n\";\n");
        $file->setExpectedOutput("new\n");

        self::assertStringContainsString("echo \"new\\n\";", $file->contents());
        self::assertStringContainsString("--EXPECT--\nnew", $file->contents());
    }

    public function testExpectedOutputNormalizesExpectfToExpect(): void
    {
        $path = $this->writeTempPhpt(<<<'PHPT'
            --TEST--
            sample
            --FILE--
            <?php
            echo "value\n";
            --EXPECTF--
            value %s
            PHPT);

        $file = new PhptFile($path, dirname($path));
        $file->setExpectedOutput('value exact');

        self::assertSame('EXPECT', $file->expectedSectionName());
        self::assertStringContainsString("--EXPECT--\nvalue exact", $file->contents());
    }

    public function testExpectedOutputPreservesMissingTerminalNewlineInLastSection(): void
    {
        $path = $this->writeTempPhpt("--TEST--\nsample\n--FILE--\n<?php\n--EXPECT--\nold");
        $file = new PhptFile($path, dirname($path));

        $file->setExpectedOutput('new');

        self::assertSame("--TEST--\nsample\n--FILE--\n<?php\n--EXPECT--\nnew", $file->contents());
    }

    public function testExpectedSectionPreservesSectionNameAndMissingTerminalNewline(): void
    {
        $path = $this->writeTempPhpt("--TEST--\nsample\n--FILE--\n<?php\n--EXPECTF--\nold %s");
        $file = new PhptFile($path, dirname($path));

        $file->setExpectedSection('EXPECTF', "new %s\n");

        self::assertSame("--TEST--\nsample\n--FILE--\n<?php\n--EXPECTF--\nnew %s", $file->contents());
    }

    public function testPreservesBinaryExpectedOutputBytes(): void
    {
        $path = $this->writeTempPhpt("--TEST--\nsample\n--FILE--\n<?php\n--EXPECTF--\n\xbd\n");
        $file = new PhptFile($path, dirname($path));

        self::assertSame("\xbd\n", $file->getSection('EXPECTF'));
        self::assertStringContainsString("--EXPECTF--\n\xbd\n", $file->contents());
    }

    public function testCanReplaceContentsAndReparseSections(): void
    {
        $path = $this->writeTempPhpt("--TEST--\nold\n--FILE--\n<?php\n--EXPECT--\nold\n");
        $file = new PhptFile($path, dirname($path));

        $file->replaceContents("--TEST--\nnew\n--FILE--\n<?php\n--EXPECTF--\nnew %s\n");

        self::assertSame('EXPECTF', $file->expectedSectionName());
        self::assertSame("new %s\n", $file->getSection('EXPECTF'));
    }

    private function writeTempPhpt(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'phpt-file-');
        self::assertIsString($path);
        self::assertNotFalse(file_put_contents($path, $contents));

        return $path;
    }
}
