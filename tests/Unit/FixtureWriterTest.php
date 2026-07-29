<?php

declare(strict_types=1);

namespace Tests\Unit;

use InternalsCS\Fixers\ExceptionOutput\Analysis\Classification;
use InternalsCS\Fixers\ExceptionOutput\Analysis\ClassificationSafety;
use InternalsCS\Fixers\ExceptionOutput\Analysis\Fingerprint;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputFamily;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputPart;
use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputParts;
use InternalsCS\Fixers\ExceptionOutput\Generation\Candidate;
use InternalsCS\Fixture\FixtureCaseName;
use InternalsCS\Fixture\FixtureSource;
use InternalsCS\Fixture\FixtureWriter;
use PHPUnit\Framework\TestCase;

use function basename;
use function bin2hex;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function hash;
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

    public function testSelectedExistingOldFixtureIsNeverUpdated(): void
    {
        $root = $this->makeTempDir();
        $source = $root . '/Zend/tests/example.phpt';
        $fixtures = $root . '/fixtures';
        mkdir(dirname($source), recursive: true);
        mkdir($fixtures);

        file_put_contents($source, "source\n");

        $candidate = $this->candidate($source, 'Zend/tests/example.phpt');
        $fixtureDir = $fixtures . '/' . new FixtureCaseName()->fromCandidate($candidate);
        mkdir($fixtureDir, recursive: true);
        file_put_contents($fixtureDir . '/old.phpt', "different\n");

        $fixtureCandidate = $this->candidate($fixtureDir . '/old.phpt', basename($fixtureDir) . '/old.phpt');
        $result = new FixtureWriter()->write(new FixtureSource([$fixtureCandidate]), $fixtures);

        self::assertFalse($result->createdOld);
        self::assertNull($result->failure);
        self::assertSame("different\n", file_get_contents($fixtureDir . '/old.phpt'));
    }

    public function testExistingHashFixtureIsUpdatedWhenDifferentSourceIsSelected(): void
    {
        $root = $this->makeTempDir();
        $source = $root . '/Zend/tests/example.phpt';
        $fixtures = $root . '/fixtures';
        mkdir(dirname($source), recursive: true);
        mkdir($fixtures);

        file_put_contents($source, "source\n");

        $candidate = $this->candidate($source, 'Zend/tests/example.phpt');
        $fixtureDir = $fixtures . '/' . new FixtureCaseName()->fromCandidate($candidate);
        mkdir($fixtureDir, recursive: true);
        file_put_contents($fixtureDir . '/old.phpt', "different\n");

        $result = new FixtureWriter()->write(new FixtureSource([$candidate]), $fixtures);

        self::assertTrue($result->createdOld);
        self::assertNull($result->failure);
        self::assertSame("source\n", file_get_contents($fixtureDir . '/old.phpt'));
    }

    public function testGeneratedFixtureCaseNameIsTheFlavourHash(): void
    {
        $source = '/tmp/php-src/Zend/tests/example.phpt';
        $candidate = $this->candidate($source, 'Zend/tests/example.phpt');

        self::assertSame(
            'flavour_' . hash('sha1', $candidate->fixtureKey),
            new FixtureCaseName()->fromCandidate($candidate),
        );
    }

    public function testFixtureSourceCaseNameIsTheFlavourHash(): void
    {
        $source = '/tmp/fixtures/old_name/old.phpt';
        $candidate = $this->candidate($source, 'old_name/old.phpt');

        self::assertSame(
            'flavour_' . hash('sha1', $candidate->fixtureKey),
            new FixtureCaseName()->fromFixtureSource(new FixtureSource([$candidate])),
        );
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
            parts: new OutputParts([OutputPart::exceptionMessage('e')], 'echo:method_call'),
            fixtureKey: $classification->fingerprint->id,
            classification: $classification,
        );
    }

    private function oldPath(string $fixtures, Candidate $candidate): string
    {
        return $fixtures . '/' . new FixtureCaseName()->fromCandidate($candidate) . '/old.phpt';
    }

    private function makeTempDir(): string
    {
        $root = sys_get_temp_dir() . '/fixture-writer-' . bin2hex(random_bytes(6));
        mkdir($root);

        return $root;
    }
}
