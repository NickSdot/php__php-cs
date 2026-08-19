<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Generation;

use InternalsCS\Fixers\ExceptionOutput\Analysis\OutputPartKind;
use InternalsCS\Fixture\FixtureCaseName;
use InternalsCS\Fixture\FixtureRewriteRunner;
use InternalsCS\Fixture\FixtureSource;
use InternalsCS\Fixture\FixtureSourceReducer;
use InternalsCS\PhpSrcTestStyle\PhptFile;
use InternalsCS\SourceFile;

use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function bin2hex;
use function class_exists;
use function count;
use function dirname;
use function escapeshellarg;
use function exec;
use function file_put_contents;
use function implode;
use function in_array;
use function mb_strlen;
use function mb_substr;
use function mkdir;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function random_bytes;
use function rmdir;
use function sort;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strcmp;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use function usort;

final readonly class MinimalFixtureSourceReducer implements FixtureSourceReducer
{
    public function __construct(
        private CandidateCollector $candidates = new CandidateCollector(),
        private FixtureCaseName $caseName = new FixtureCaseName(),
    ) {}

    public function reduce(FixtureSource $source, FixtureRewriteRunner $runner): ?string
    {
        $fixtureCaseKeys = $source->fixtureCaseKeys();

        if (1 !== count($fixtureCaseKeys)) {
            return null;
        }

        $fixtureCase = $fixtureCaseKeys[0];
        $candidates = $this->exceptionCandidates($source);

        if (!$this->isRepresentedInExpectedOutput($source, $candidates)) {
            return null;
        }

        usort(
            $candidates,
            static function (Candidate $a, Candidate $b): int {
                $lengthComparison = mb_strlen($a->statement) <=> mb_strlen($b->statement);

                return 0 !== $lengthComparison ? $lengthComparison : strcmp($a->statement, $b->statement);
            },
        );

        foreach ($candidates as $candidate) {
            if ($candidate->fixtureCaseKey !== $fixtureCase) {
                continue;
            }

            try {
                $fixture = $this->minimalFixture($this->caseName->fromFixtureSource($source), $candidate);
            } catch (\Throwable) {
                continue;
            }

            if ($this->isRewritableFixture($fixture, $fixtureCase, $runner)) {
                return $fixture;
            }
        }

        return null;
    }

    /** @return list<Candidate> */
    private function exceptionCandidates(FixtureSource $source): array
    {
        $candidates = [];

        foreach ($source->candidates as $candidate) {
            if (!$candidate instanceof Candidate) {
                throw new \LogicException('Exception-output reducer received a non exception-output candidate');
            }

            $candidates[] = $candidate;
        }

        return $candidates;
    }

    /** @param list<Candidate> $candidates */
    private function isRepresentedInExpectedOutput(FixtureSource $source, array $candidates): bool
    {
        $expected = $this->expectedOutput($source);

        if (null === $expected) {
            return false;
        }

        foreach ($candidates as $candidate) {
            if (!$candidate->isRepresentedInExpectedOutput($expected)) {
                return false;
            }
        }

        return true;
    }

    private function expectedOutput(FixtureSource $source): ?string
    {
        $file = new PhptFile($source->sourcePath, dirname($source->sourcePath));
        $section = $file->expectedSectionName();

        return null === $section ? null : $file->getSection($section);
    }

    private function minimalFixture(string $case, Candidate $candidate): string
    {
        $statement = $candidate->statement;
        $exceptionVariables = $this->exceptionVariables($candidate);
        $catch = $candidate->catchVariable;
        $throwExpression = $this->throwExpression($candidate);
        $catchType = $this->catchType($candidate);
        $inlineOutput = str_contains($candidate->fixtureCaseKey, 'following-inline-output')
            ? "following inline output\n"
            : '';
        $expectedSection = str_contains($candidate->fixtureCaseKey, '|expected:expect:following-inline-output')
            ? 'EXPECT'
            : 'EXPECTF';

        $code = "<?php\n"
            . $this->classDefinition($candidate)
            . implode('', $this->variableAssignments($statement, $exceptionVariables, $catch))
            . $this->previousOutput($candidate->fixtureContext)
            . "try {\n"
            . $this->tryStatement($candidate->fixtureContext, $throwExpression)
            . "} catch ($catchType \$$catch) {\n"
            . implode('', $this->exceptionAssignments($exceptionVariables, $catch))
            . $this->indentStatement($statement)
            . "\n"
            . $this->followingTrace($candidate->fixtureContext, $catch)
            . "}\n"
            . $this->followingOutput($candidate->fixtureContext)
            . "?>\n"
            . $inlineOutput;

        return "--TEST--\n"
            . "Exception output: $case\n"
            . "--FILE--\n"
            . $code
            . "--$expectedSection--\n"
            . $this->expectedOutputFor($code, 'EXPECTF' === $expectedSection);
    }

    private function previousOutput(FixtureContext $context): string
    {
        return PreviousSeparatorContext::None === $context->previousSeparator
            ? ''
            : "echo 'preceding output';\n";
    }

    private function tryStatement(FixtureContext $context, string $throwExpression): string
    {
        return PreviousSeparatorContext::BlockedByTryOutput === $context->previousSeparator
            ? "    var_dump(throw $throwExpression);\n"
            : "    throw $throwExpression;\n";
    }

    private function followingTrace(FixtureContext $context, string $catchVariable): string
    {
        return $context->followingTraceOutput
            ? "    print_r(\$" . $catchVariable . "->getTrace());\n"
            : '';
    }

    private function followingOutput(FixtureContext $context): string
    {
        return $context->followingNewlineMoved
            ? "echo \"\\nfollowing output\\n\";\n"
            : '';
    }

    private function throwExpression(Candidate $candidate): string
    {
        $class = $this->throwClass($candidate);

        $message = $candidate->expectation->isEmpty(OutputPartKind::ExceptionMessage)
            ? ''
            : 'fixture message';

        if ('\\SoapFault' === $class) {
            return "new \\SoapFault('fixture fault', '$message')";
        }

        return "new $class('$message')";
    }

    private function classDefinition(Candidate $candidate): string
    {
        $class = $this->throwClass($candidate);
        $name = str_starts_with($class, '\\') ? mb_substr($class, 1, null, '8bit') : $class;

        if (class_exists($name)) {
            return '';
        }

        if (str_contains($name, '\\')) {
            return '';
        }

        if ('SoapFault' === $name) {
            return "if (!class_exists('$name')) { class $name extends \\RuntimeException { public function __construct(string \$faultcode, string \$faultstring) { parent::__construct(\$faultstring); } } }\n";
        }

        return "if (!class_exists('$name')) { class $name extends \\RuntimeException {} }\n";
    }

    private function throwClass(Candidate $candidate): string
    {
        $type = $candidate->catchTypes[0] ?? null;

        if (null === $type || 'Throwable' === $type || '\\Throwable' === $type || 'Exception' === $type || '\\Exception' === $type) {
            return '\\RuntimeException';
        }

        return $this->fullyQualified($type);
    }

    private function catchType(Candidate $candidate): string
    {
        if ([] === $candidate->catchTypes) {
            return '\\Throwable';
        }

        return implode('|', array_map($this->fullyQualified(...), $candidate->catchTypes));
    }

    private function fullyQualified(string $type): string
    {
        return str_starts_with($type, '\\') ? $type : '\\' . $type;
    }

    /** @return list<string> */
    private function exceptionVariables(Candidate $candidate): array
    {
        $variables = [];

        foreach ($candidate->parts->parts as $part) {
            if (null === $part->variable) {
                continue;
            }

            if (!in_array($part->kind, [
                OutputPartKind::ExceptionClass,
                OutputPartKind::ExceptionMessage,
                OutputPartKind::ExceptionCode,
                OutputPartKind::ExceptionFile,
                OutputPartKind::ExceptionLine,
                OutputPartKind::ExceptionTrace,
            ], true)) {
                continue;
            }

            $variables[$part->variable] = true;
        }

        return array_keys($variables);
    }

    /**
     * @param list<string> $exceptionVariables
     * @return list<string>
     */
    private function variableAssignments(string $statement, array $exceptionVariables, string $catch): array
    {
        preg_match_all('/\$([A-Za-z_][A-Za-z0-9_]*)/', $statement, $matches);

        $variables = array_values(array_unique($matches[1]));
        sort($variables);
        $assignments = [];

        foreach ($variables as $variable) {
            if ($variable === $catch || in_array($variable, $exceptionVariables, true)) {
                continue;
            }

            if (1 === preg_match('/\$' . preg_quote($variable, '/') . '->getName\s*\(/', $statement)) {
                $assignments[] = "\$$variable = new class { public function getName(): string { return 'fixture'; } };\n";
                continue;
            }

            $assignments[] = "\$$variable = 'fixture';\n";
        }

        return $assignments;
    }

    /**
     * @param list<string> $exceptionVariables
     * @return list<string>
     */
    private function exceptionAssignments(array $exceptionVariables, string $catch): array
    {
        $assignments = [];

        foreach ($exceptionVariables as $variable) {
            if ($variable === $catch) {
                continue;
            }

            $assignments[] = "    \$$variable = new \\RuntimeException('fixture message');\n";
        }

        return $assignments;
    }

    private function indentStatement(string $statement): string
    {
        return '    ' . str_replace("\n", "\n    ", $statement);
    }

    private function expectedOutputFor(string $code, bool $expectf): string
    {
        $path = tempnam(sys_get_temp_dir(), 'minimal-fixture-');

        if (false === $path) {
            throw new \RuntimeException('Cannot create temporary PHP file');
        }

        file_put_contents($path, $code);

        try {
            $command = escapeshellarg(\PHP_BINARY)
                . ' -d display_errors=1 -d error_reporting=32767 '
                . escapeshellarg($path);
            exec($command . ' 2>&1', $lines, $exitCode);
            $output = implode("\n", $lines);

            if ([] !== $lines) {
                $output .= "\n";
            }

            if (0 !== $exitCode) {
                throw new \RuntimeException("Minimal fixture PHP failed:\n$code\n$output");
            }

            return $expectf ? $this->normaliseExpectfOutput($output, $path) : $output;
        } finally {
            unlink($path);
        }
    }

    private function normaliseExpectfOutput(string $output, string $path): string
    {
        $output = str_replace($path, '%s', $output);
        $output = (string) preg_replace('/%s\(\d+\)/', '%s(%d)', $output);
        $output = (string) preg_replace('/%s:\d+/', '%s:%d', $output);
        $output = (string) preg_replace('/(?m)^\d+:/', '%d:', $output);
        $output = (string) preg_replace('/fixture message\(\d+\)/', 'fixture message(%d)', $output);

        return (string) preg_replace('/ on line \d+/', ' on line %d', $output);
    }

    private function isRewritableFixture(string $contents, string $fixtureCase, FixtureRewriteRunner $runner): bool
    {
        $dir = sys_get_temp_dir() . '/internals-cs-minimal-fixture-' . bin2hex(random_bytes(12));
        $path = $dir . '/old.phpt';

        mkdir($dir);
        file_put_contents($path, $contents);

        try {
            if (!$this->hasExactFixtureCase($path, $dir, $fixtureCase)) {
                return false;
            }

            $rewrite = $runner->printFile($path);

            return $rewrite['changed'] && !$rewrite['failed'];
        } finally {
            unlink($path);
            rmdir($dir);
        }
    }

    private function hasExactFixtureCase(string $path, string $rootDir, string $fixtureCase): bool
    {
        $keys = [];

        foreach ($this->candidates->collect(new SourceFile($path, $rootDir)) as $candidate) {
            $keys[$candidate->fixtureCaseKey] = true;
        }

        $keys = array_keys($keys);
        sort($keys);

        return [$fixtureCase] === $keys;
    }
}
