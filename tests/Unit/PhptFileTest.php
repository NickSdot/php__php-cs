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
        self::assertStringContainsString('echo "old\n";', $file->getSection('FILE'));

        $file->setSection('FILE', "<?php\necho \"new\\n\";\n");
        $file->setExpectedOutput("new\n");

        self::assertStringContainsString("echo \"new\\n\";", $file->contents());
        self::assertStringContainsString("--EXPECT--\nnew\n", $file->contents());
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
        self::assertStringContainsString("--EXPECT--\nvalue exact\n", $file->contents());
    }

    private function writeTempPhpt(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'phpt-file-');
        self::assertIsString($path);
        self::assertNotFalse(file_put_contents($path, $contents));

        return $path;
    }
}
