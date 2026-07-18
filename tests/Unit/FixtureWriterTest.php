<?php

declare(strict_types=1);

namespace Tests\Unit;

use InternalsCS\Fixture\FixtureCaseName;
use InternalsCS\Fixture\FixtureSource;
use InternalsCS\Fixture\FixtureWriter;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\Classification;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\ClassificationSafety;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\Fingerprint;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis\OutputFamily;
use InternalsCS\PhpSrcTestStyle\ExceptionOutput\Generation\Candidate;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

final class FixtureWriterTest extends TestCase
{
    public function testOldFixtureIsCreatedAsExactSourceCopy(): void
    {
        $root = $this->makeTempDir();
        $source = $root . '/Zend/tests/example.phpt';
        $fixtures = $root . '/fixtures';
        mkdir(dirname($source), recursive: true);
        mkdir($fixtures);

        $contents = "--TEST--\nexample\n--FILE--\n<?php\ntry {} catch (Error \$e) { echo \$e->getMessage(), \"\\n\"; }\n--EXPECT--\n";
        file_put_contents($source, $contents);

        $candidate = $this->candidate($source, 'Zend/tests/example.phpt');
        $result = new FixtureWriter()->write(new FixtureSource([$candidate]), $fixtures);

        self::assertTrue($result->createdOld);
        self::assertSame($contents, file_get_contents($this->oldPath($fixtures, $candidate)));
    }

    public function testExistingOldFixtureIsNeverUpdatedFromSource(): void
    {
        $root = $this->makeTempDir();
        $source = $root . '/Zend/tests/example.phpt';
        $fixtures = $root . '/fixtures';
        mkdir(dirname($source), recursive: true);
        mkdir($fixtures);

        file_put_contents($source, "source\n");

        $candidate = $this->candidate($source, 'Zend/tests/example.phpt');
        $fixtureDir = $fixtures . '/' . new FixtureCaseName()->fromSourcePath($candidate->relativePath);
        mkdir($fixtureDir, recursive: true);
        file_put_contents($fixtureDir . '/old.phpt', "different\n");

        $result = new FixtureWriter()->write(new FixtureSource([$candidate]), $fixtures);

        self::assertFalse($result->createdOld);
        self::assertNull($result->failure);
        self::assertSame("different\n", file_get_contents($fixtureDir . '/old.phpt'));
    }

    public function testFixtureCaseNamesPreserveLeadingUnderscoreSegments(): void
    {
        $root = $this->makeTempDir();
        $source = $root . '/Zend/tests/asymmetric_visibility/__unset.phpt';
        $fixtures = $root . '/fixtures';
        mkdir(dirname($source), recursive: true);
        mkdir($fixtures);

        file_put_contents($source, "source\n");

        $candidate = $this->candidate($source, 'Zend/tests/asymmetric_visibility/__unset.phpt');

        new FixtureWriter()->write(new FixtureSource([$candidate]), $fixtures);

        self::assertFileExists($this->oldPath($fixtures, $candidate));
    }

    public function testFixtureCaseNameIsOnlyTheSourcePathSlug(): void
    {
        $source = '/tmp/php-src/Zend/tests/example.phpt';
        $candidate = $this->candidate($source, 'Zend/tests/example.phpt');

        self::assertSame('Zend_tests_example', new FixtureCaseName()->fromCandidate($candidate));
    }

    public function testManualOldFixtureCaseNameIsTheManualFixtureDirectory(): void
    {
        self::assertSame('manual_001', new FixtureCaseName()->fromSourcePath('manual_001/old.phpt'));
    }

    private function candidate(string $source, string $relativePath): Candidate
    {
        $classification = new Classification(
            family: OutputFamily::MessageOnly,
            safety: ClassificationSafety::Fixable,
            fingerprint: new Fingerprint(OutputFamily::MessageOnly, 'test-payload'),
        );

        return new Candidate(
            sourcePath: $source,
            relativePath: $relativePath,
            line: 1,
            statement: 'echo $e->getMessage();',
            key: $classification->fingerprint->id,
            classification: $classification,
        );
    }

    private function oldPath(string $fixtures, Candidate $candidate): string
    {
        return $fixtures . '/' . new FixtureCaseName()->fromSourcePath($candidate->relativePath) . '/old.phpt';
    }

    private function makeTempDir(): string
    {
        $root = sys_get_temp_dir() . '/fixture-writer-' . bin2hex(random_bytes(6));
        mkdir($root);

        return $root;
    }
}
