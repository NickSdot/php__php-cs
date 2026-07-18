<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing;

use InternalsCS\PhpSrcTestStyle\ExpectedOutputUpdate;

use function array_any;
use function array_pop;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function implode;
use function in_array;
use function mb_rtrim;
use function mb_strlen;
use function mb_strpos;
use function mb_substr;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function stripcslashes;

final readonly class CanonicalUpdater
{
    public function update(string $section, string $expected, string $actual): ExpectedOutputUpdate
    {
        if ('EXPECT' === $section) {
            return $this->updateExpect($expected, $actual);
        }

        if ('EXPECTF' === $section) {
            return $this->updateExpectf($expected, $actual);
        }

        return ExpectedOutputUpdate::failed("section $section is not supported by this fixer yet");
    }

    private function updateExpect(string $expected, string $actual): ExpectedOutputUpdate
    {
        $expectedLines = $this->lines($expected);
        $actualLines = $this->lines($actual);

        foreach ($this->expectedLineSequences($expectedLines) as $candidateLines) {
            $updated = $this->updatedExpectLines($candidateLines, $actualLines);

            if (null !== $updated) {
                return ExpectedOutputUpdate::changed($this->join($updated));
            }
        }

        return ExpectedOutputUpdate::failed(
            'expected output did not match a canonical exception-output rewrite'
                . '; expected ' . count($expectedLines) . ' line(s), actual ' . count($actualLines) . ' line(s)',
        );
    }

    private function updateExpectf(string $expected, string $actual): ExpectedOutputUpdate
    {
        $expectedLines = $this->lines($expected);
        $actualLines = $this->lines($actual);

        foreach ($this->expectedLineSequences($expectedLines) as $candidateLines) {
            $updated = $this->updatedExpectfLines($candidateLines, $actualLines);

            if (null !== $updated) {
                return ExpectedOutputUpdate::changed($this->join($updated));
            }
        }

        return ExpectedOutputUpdate::failed(
            'EXPECTF output did not match a canonical exception-output rewrite'
                . '; expected ' . count($expectedLines) . ' line(s), actual ' . count($actualLines) . ' line(s)',
        );
    }

    /**
     * @param list<string> $expectedLines
     * @param list<string> $actualLines
     * @return list<string>|null
     */
    private function updatedExpectLines(array $expectedLines, array $actualLines): ?array
    {
        $updated = [];
        $expectedIndex = 0;
        $actualIndex = 0;

        while ($expectedIndex < count($expectedLines) && $actualIndex < count($actualLines)) {
            $expectedLine = $expectedLines[$expectedIndex];
            $actualLine = $actualLines[$actualIndex];

            if ($expectedLine === $actualLine) {
                $updated[] = $actualLine;
                $expectedIndex++;
                $actualIndex++;
                continue;
            }

            if ($this->lineCanCanonicalizeToActual($expectedLine, $actualLine, exact: true)) {
                $updated[] = $actualLine;
                $expectedIndex++;
                $actualIndex++;
                continue;
            }

            if ('' === $expectedLine) {
                $expectedIndex++;
                continue;
            }

            return null;
        }

        return $this->remainingLinesAreBlank($expectedLines, $expectedIndex) && $actualIndex === count($actualLines)
            ? $updated
            : null;
    }

    /**
     * @param list<string> $expectedLines
     * @param list<string> $actualLines
     * @return list<string>|null
     */
    private function updatedExpectfLines(array $expectedLines, array $actualLines): ?array
    {
        $updated = [];
        $expectedIndex = 0;
        $actualIndex = 0;

        while ($expectedIndex < count($expectedLines) && $actualIndex < count($actualLines)) {
            $expectedLine = $expectedLines[$expectedIndex];
            $actualLine = $actualLines[$actualIndex];

            if ($this->expectfLineMatches($expectedLine, $actualLine)) {
                $updated[] = $expectedLine;
                $expectedIndex++;
                $actualIndex++;
                continue;
            }

            $updatedLine = $this->updatedExpectfLine($expectedLine, $actualLine);

            if (null !== $updatedLine) {
                $updated[] = $updatedLine;
                $expectedIndex++;
                $actualIndex++;
                continue;
            }

            if ('' === $expectedLine) {
                $expectedIndex++;
                continue;
            }

            return null;
        }

        return $this->remainingLinesAreBlank($expectedLines, $expectedIndex) && $actualIndex === count($actualLines)
            ? $updated
            : null;
    }

    /** @param list<string> $lines */
    private function remainingLinesAreBlank(array $lines, int $offset): bool
    {
        for ($i = $offset; $i < count($lines); $i++) {
            if ('' !== $lines[$i]) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $lines
     * @return list<list<string>>
     */
    private function expectedLineSequences(array $lines): array
    {
        $sequences = [$lines];
        $merged = $this->mergeClassThenMessageLines($lines);

        if ($merged !== $lines) {
            $sequences[] = $merged;
        }

        foreach ($sequences as $sequence) {
            $merged = $this->mergeVarDumpMarkerMessageLines($sequence);

            if (!in_array($merged, $sequences, true)) {
                $sequences[] = $merged;
            }
        }

        foreach ($sequences as $sequence) {
            $merged = $this->mergeVarDumpCodeMessageLines($sequence);

            if (!in_array($merged, $sequences, true)) {
                $sequences[] = $merged;
            }
        }

        return $sequences;
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function mergeClassThenMessageLines(array $lines): array
    {
        $merged = [];

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            $nextLine = $lines[$i + 1] ?? null;

            if (null !== $nextLine && $this->isClassOnlyLine($line)) {
                $merged[] = $line . ': ' . $nextLine;
                $i++;
                continue;
            }

            $merged[] = $line;
        }

        return $merged;
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function mergeVarDumpMarkerMessageLines(array $lines): array
    {
        $merged = [];

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            $nextLine = $lines[$i + 1] ?? null;

            if (null !== $nextLine && $this->isVarDumpErrorMarker($line) && 1 === preg_match('/^string\((?:\d+|%d)\) "(.*)"$/', $nextLine, $matches)) {
                $merged[] = $this->varDumpStringValue($line) . ': ' . stripcslashes($matches[1]);
                $i++;
                continue;
            }

            $merged[] = $line;
        }

        return $merged;
    }

    private function isVarDumpErrorMarker(string $line): bool
    {
        return 1 === preg_match('/^string\((?:\d+|%d)\) "ERROR \d+"$/', $line);
    }

    private function varDumpStringValue(string $line): string
    {
        if (1 === preg_match('/^string\((?:\d+|%d)\) "(.*)"$/', $line, $matches)) {
            return stripcslashes($matches[1]);
        }

        throw new \LogicException('Expected a var_dump string line');
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function mergeVarDumpCodeMessageLines(array $lines): array
    {
        $merged = [];

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            $nextLine = $lines[$i + 1] ?? null;

            if (null !== $nextLine && 1 === preg_match('/^int\((-?\d+)\)$/', $line, $code) && 1 === preg_match('/^string\((?:\d+|%d)\) "(.*)"$/', $nextLine, $message)) {
                $merged[] = $code[1] . ', ' . stripcslashes($message[1]);
                $i++;
                continue;
            }

            $merged[] = $line;
        }

        return $merged;
    }

    private function lineCanCanonicalizeToActual(string $expectedLine, string $actualLine, bool $exact): bool
    {
        foreach ($this->canonicalLines($actualLine) as $actual) {
            foreach ($this->oldLineCandidates($expectedLine) as $candidate) {
                if ($this->candidateMatchesActual($candidate, $actual, $exact)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function updatedExpectfLine(string $expectedLine, string $actualLine): ?string
    {
        foreach ($this->canonicalLines($actualLine) as $actual) {
            foreach ($this->oldLineCandidates($expectedLine) as $candidate) {
                if (!$this->candidateMatchesActual($candidate, $actual, exact: false)) {
                    continue;
                }

                return $this->canonicalExpectfLine($candidate, $actual);
            }
        }

        return null;
    }

    /** @return list<array{prefix: string, class: string, message: string, code: string|null, file: string|null, line: string|null}> */
    private function canonicalLines(string $line): array
    {
        if (false === preg_match_all(
            '/([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)(?:\(((?:[+-]?\d+|%d|%i))\))?: /',
            $line,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL,
        )) {
            return [];
        }

        $canonical = [];

        foreach ($matches as $match) {
            $fullMatch = $match[0][0] ?? null;
            $class = $match[1][0] ?? null;
            $classOffset = $match[1][1];

            if (null === $fullMatch || null === $class || $classOffset < 0) {
                continue;
            }

            if (!$this->isLikelyExceptionClass($class)) {
                continue;
            }

            $message = mb_substr($line, $classOffset + mb_strlen($fullMatch));
            $code = $match[2][0] ?? null;
            $sourceFile = null;
            $sourceLine = null;

            [$message, $code] = $this->extractCode($message, $code);
            [$message, $sourceFile, $sourceLine] = $this->extractLocation($message);

            $canonical[] = [
                'prefix' => mb_substr($line, 0, $classOffset),
                'class' => $class,
                'message' => $message,
                'code' => $code,
                'file' => $sourceFile,
                'line' => $sourceLine,
            ];
        }

        return $canonical;
    }

    /** @return array{string, string|null} */
    private function extractCode(string $message, ?string $code): array
    {
        if (1 === preg_match('/^((?:[+-]?\d+|%d|%i)): (.+)$/', $message, $matches)) {
            return [$matches[2], $code ?? $matches[1]];
        }

        if (1 === preg_match('/^(.*), code: (.+)$/', $message, $matches)) {
            return [$matches[1], $code ?? $matches[2]];
        }

        return [$message, $code];
    }

    /** @return array{string, string|null, string|null} */
    private function extractLocation(string $message): array
    {
        if (1 === preg_match('/^(.*) in (.+) on line (\d+)$/', $message, $matches)) {
            return [$matches[1], $matches[2], $matches[3]];
        }

        if (1 === preg_match('/^(.*) on line (\d+)$/', $message, $matches)) {
            return [$matches[1], null, $matches[2]];
        }

        return [$message, null, null];
    }

    /** @return list<string> */
    private function oldLineCandidates(string $line): array
    {
        $candidates = [$line];
        $queue = [$line];

        while ([] !== $queue) {
            $current = array_pop($queue);

            foreach ([...$this->replaceOneContextWrapper($current), ...$this->stripOneTrashWrapper($current)] as $candidate) {
                if (in_array($candidate, $candidates, true)) {
                    continue;
                }

                $candidates[] = $candidate;
                $queue[] = $candidate;
            }
        }

        return $candidates;
    }

    /** @return list<string> */
    private function replaceOneContextWrapper(string $line): array
    {
        $candidates = [];

        if (str_starts_with($line, 'Expected exception for class-based reflection: ')) {
            $candidates[] = 'class-based reflection: ' . mb_substr($line, mb_strlen('Expected exception for class-based reflection: '));
        }

        if (1 === preg_match('/^Exception thrown for ([^:]+): (.*)$/', $line, $matches)) {
            $candidates[] = $matches[1] . ': ' . $matches[2];
        }

        if (1 === preg_match('/^Unexpected exception: (.*)$/i', $line, $matches)) {
            $candidates[] = 'unexpected: ' . $matches[1];
        }

        return $candidates;
    }

    /** @return list<string> */
    private function stripOneTrashWrapper(string $line): array
    {
        $candidates = [];
        $prefixes = [
            '*** Caught ',
            'assert(): ',
            'Caught Exception: ',
            'Caught FatalException: ',
            'Caught exception with message "',
            'Exception caught: ',
            'Exception thrown: ',
            'RuntimeException thrown: ',
            'LogicException: ',
            'EXCEPTION: ',
            'ERROR: ',
            'unexpected exception: ',
            'Unexpected exception: ',
            'expected exception: ',
            'Assertion failure: ',
            'Error found: ',
            'ERR ',
            'Caught in ',
            'Caught: ',
            'Caught ',
            'caught ',
            '[Error] ',
            'Error: ',
            'Exception: ',
            'in catch: ',
            'Ok - ',
            'OK! ',
            'Parse error: ',
            'PDOException message: ',
            'Safely caught ',
            'TEST:',
            'TEST: ',
        ];
        $suffixes = ['<br />', '<br>', ' failed', '()"', '()', '".', '"', '.'];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($line, $prefix)) {
                $candidates[] = mb_substr($line, mb_strlen($prefix));
            }

            $offset = mb_strpos($line, $prefix);

            if ($offset > 0 && str_ends_with(mb_substr($line, 0, $offset), ':')) {
                $candidates[] = mb_substr($line, 0, $offset) . mb_substr($line, $offset + mb_strlen($prefix));
            }

            $indentedPrefix = '  ' . $prefix;

            if (str_starts_with($line, $indentedPrefix)) {
                $candidates[] = mb_substr($line, mb_strlen($indentedPrefix));
            }
        }

        foreach ($suffixes as $suffix) {
            if (str_ends_with($line, $suffix)) {
                $candidates[] = mb_substr($line, 0, -mb_strlen($suffix));
            }
        }

        if (1 === preg_match('/^\[([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)\] (.*)$/', $line, $matches)) {
            $candidates[] = $matches[1] . ': ' . $matches[2];
            $candidates[] = $matches[2];
        }

        if (1 === preg_match('/^Exception \(([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)\): (.*)$/', $line, $matches)) {
            $candidates[] = $matches[1] . ': ' . $matches[2];
            $candidates[] = $matches[2];
        }

        if (1 === preg_match('/^([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)\s+:\s+(.*)$/', $line, $matches) && $this->isLikelyExceptionClass($matches[1])) {
            $candidates[] = $matches[1] . ': ' . $matches[2];
            $candidates[] = $matches[2];
        }

        if (1 === preg_match('/^(.*?)([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)\s+:\s+(.*)$/', $line, $matches) && $this->isLikelyExceptionClass($matches[2])) {
            $candidates[] = $matches[1] . $matches[2] . ': ' . $matches[3];
        }

        if (1 === preg_match('/^([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)\((.*)\)$/', $line, $matches) && $this->isLikelyExceptionClass($matches[1])) {
            $candidates[] = $matches[1] . ': ' . $matches[2];
            $candidates[] = $matches[2];
        }

        if (1 === preg_match('/^string\((?:\d+|%d)\) "(.*)"$/', $line, $matches)) {
            $candidates[] = stripcslashes($matches[1]);
        }

        return array_values(array_unique($candidates));
    }

    /** @param array{prefix: string, class: string, message: string, code: string|null, file: string|null, line: string|null} $actual */
    private function candidateMatchesActual(string $candidate, array $actual, bool $exact): bool
    {
        return array_any($this->expectedForms($actual), fn($form) => $exact ? $candidate === $form : $this->expectfLineMatches($candidate, $form));
    }

    /** @param array{prefix: string, class: string, message: string, code: string|null, file: string|null, line: string|null} $actual */
    private function canonicalExpectfLine(string $candidate, array $actual): string
    {
        $prefix = $actual['prefix'];
        $class = $actual['class'];
        $candidateBody = $this->candidateBody($candidate, $prefix);
        $code = $actual['code'];

        if (null !== $code && 1 === preg_match('/^' . preg_quote($class, '/') . ': ((?:[+-]?\d+|%d|%i)): (.+)$/', $candidateBody, $matches)) {
            return $prefix . $class . ': ' . $matches[1] . ': ' . $matches[2];
        }

        if (null !== $code && 1 === preg_match('/^' . preg_quote($class, '/') . '\(((?:[+-]?\d+|%d|%i))\): (.+)$/', $candidateBody, $matches)) {
            return $prefix . $class . ': ' . $matches[1] . ': ' . $matches[2];
        }

        if (null !== $code && 1 === preg_match('/^' . preg_quote($class, '/') . ': (.+), code: ((?:[+-]?\d+|%d|%i))$/', $candidateBody, $matches)) {
            return $prefix . $class . ': ' . $matches[2] . ': ' . $matches[1];
        }

        if (null !== $code && 1 === preg_match('/^((?:[+-]?\d+|%d|%i)), (.+)$/', $candidateBody, $matches)) {
            return $prefix . $class . ': ' . $matches[1] . ': ' . $matches[2];
        }

        if (null !== $code && 1 === preg_match('/^' . preg_quote($class, '/') . ': ((?:[+-]?\d+|%d|%i)), (.+)$/', $candidateBody, $matches)) {
            return $prefix . $class . ': ' . $matches[1] . ': ' . $matches[2];
        }

        if (null !== $code && 1 === preg_match('/^((?:[+-]?\d+|%d|%i)): (.+)$/', $candidateBody, $matches)) {
            return $prefix . $class . ': ' . $matches[1] . ': ' . $matches[2];
        }

        if (1 === preg_match('/^((?:[+-]?\d+|%d|%i)): (.+)$/', $candidateBody, $matches)) {
            return $prefix . $class . ': ' . $matches[2] . ' on line ' . $matches[1];
        }

        if (1 === preg_match('/^(.*)\(((?:[+-]?\d+|%d|%i))\)$/', $candidateBody, $matches)) {
            return $prefix . $class . ': ' . $matches[1] . ' on line ' . $matches[2];
        }

        if (1 === preg_match('/^(.*) at (.+):((?:[+-]?\d+|%d|%i))$/', $candidateBody, $matches)) {
            return $prefix . $class . ': ' . $matches[1] . ' in ' . $matches[2] . ' on line ' . $matches[3];
        }

        if (1 === preg_match('/^(.*) in (.+) on line ((?:[+-]?\d+|%d|%i))$/', $candidateBody, $matches)) {
            return $prefix . $class . ': ' . $matches[1] . ' in ' . $matches[2] . ' on line ' . $matches[3];
        }

        if (str_starts_with($candidateBody, $class . ': ')) {
            return $prefix . $candidateBody;
        }

        return $prefix . $class . ': ' . $candidateBody;
    }

    private function candidateBody(string $candidate, string $prefix): string
    {
        if ('' === $prefix || !str_starts_with($candidate, $prefix)) {
            return $candidate;
        }

        return mb_substr($candidate, mb_strlen($prefix));
    }

    /**
     * @param array{prefix: string, class: string, message: string, code: string|null, file: string|null, line: string|null} $actual
     * @return list<string>
     */
    private function expectedForms(array $actual): array
    {
        $classMessage = $actual['class'] . ': ' . $actual['message'];
        $forms = [];

        if (null !== $actual['code']) {
            $classMessageCode = $actual['class'] . ': ' . $actual['code'] . ': ' . $actual['message'];
            $classMessageParenthesizedCode = $actual['class'] . '(' . $actual['code'] . '): ' . $actual['message'];
            $classMessageTrailingCode = $classMessage . ', code: ' . $actual['code'];

            $forms[] = $classMessageCode;
            $forms[] = $actual['prefix'] . $classMessageCode;
            $forms[] = $classMessageParenthesizedCode;
            $forms[] = $actual['prefix'] . $classMessageParenthesizedCode;
            $forms[] = $classMessageTrailingCode;
            $forms[] = $actual['prefix'] . $classMessageTrailingCode;
            $forms[] = $actual['code'] . ', ' . $actual['message'];
            $forms[] = $actual['prefix'] . $actual['code'] . ', ' . $actual['message'];
            $forms[] = $actual['class'] . ': ' . $actual['code'] . ', ' . $actual['message'];
            $forms[] = $actual['prefix'] . $actual['class'] . ': ' . $actual['code'] . ', ' . $actual['message'];
            $forms[] = $actual['code'] . ': ' . $actual['message'];
            $forms[] = $actual['prefix'] . $actual['code'] . ': ' . $actual['message'];
        } else {
            $forms[] = $actual['message'];
            $forms[] = $classMessage;
            $forms[] = $actual['prefix'] . $actual['message'];
            $forms[] = $actual['prefix'] . $classMessage;
        }

        if (null !== $actual['line']) {
            foreach ($this->lineForms($actual, $classMessage) as $form) {
                $forms[] = $form;
            }
        }

        if (null !== $actual['file'] && null !== $actual['line']) {
            foreach ($this->fileLineForms($actual, $classMessage) as $form) {
                $forms[] = $form;
            }
        }

        return array_values(array_unique($forms));
    }

    /**
     * @param array{prefix: string, class: string, message: string, code: string|null, file: string|null, line: string|null} $actual
     * @return list<string>
     */
    private function lineForms(array $actual, string $classMessage): array
    {
        if (null === $actual['line']) {
            return [];
        }

        $message = $actual['message'];

        if (null !== $actual['code']) {
            $message = $actual['code'] . ': ' . $message;
            $classMessage = $actual['class'] . ': ' . $message;
        }

        return [
            $message . ' on line ' . $actual['line'],
            $classMessage . ' on line ' . $actual['line'],
            $actual['prefix'] . $message . ' on line ' . $actual['line'],
            $actual['prefix'] . $classMessage . ' on line ' . $actual['line'],
            $actual['line'] . ': ' . $message,
            $actual['line'] . ': ' . $classMessage,
            $actual['prefix'] . $actual['line'] . ': ' . $message,
            $actual['prefix'] . $actual['line'] . ': ' . $classMessage,
            $message . '(' . $actual['line'] . ')',
            $classMessage . '(' . $actual['line'] . ')',
            $actual['prefix'] . $message . '(' . $actual['line'] . ')',
            $actual['prefix'] . $classMessage . '(' . $actual['line'] . ')',
        ];
    }

    /**
     * @param array{prefix: string, class: string, message: string, code: string|null, file: string|null, line: string|null} $actual
     * @return list<string>
     */
    private function fileLineForms(array $actual, string $classMessage): array
    {
        if (null === $actual['file'] || null === $actual['line']) {
            return [];
        }

        $message = $actual['message'];

        if (null !== $actual['code']) {
            $message = $actual['code'] . ': ' . $message;
            $classMessage = $actual['class'] . ': ' . $message;
        }

        return [
            $message . ' in ' . $actual['file'] . ' on line ' . $actual['line'],
            $classMessage . ' in ' . $actual['file'] . ' on line ' . $actual['line'],
            $actual['prefix'] . $message . ' in ' . $actual['file'] . ' on line ' . $actual['line'],
            $actual['prefix'] . $classMessage . ' in ' . $actual['file'] . ' on line ' . $actual['line'],
            $message . ' at ' . $actual['file'] . ':' . $actual['line'],
            $classMessage . ' at ' . $actual['file'] . ':' . $actual['line'],
            $actual['prefix'] . $message . ' at ' . $actual['file'] . ':' . $actual['line'],
            $actual['prefix'] . $classMessage . ' at ' . $actual['file'] . ':' . $actual['line'],
        ];
    }

    private function isClassOnlyLine(string $line): bool
    {
        return 1 === preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/', $line)
            && $this->isLikelyExceptionClass($line);
    }

    private function isLikelyExceptionClass(string $class): bool
    {
        return 1 === preg_match('/(?:Exception|Error|Throwable|SoapFault)$/', $class);
    }

    private function expectfLineMatches(string $expected, string $actual): bool
    {
        return 1 === preg_match('/^' . $this->expectfToRegex($expected) . '$/s', $actual);
    }

    private function expectfToRegex(string $expected): string
    {
        $regex = '';
        $length = mb_strlen($expected, '8bit');

        for ($i = 0; $i < $length; $i++) {
            if ('%' !== $expected[$i] || $i + 1 >= $length) {
                $regex .= preg_quote($expected[$i], '/');
                continue;
            }

            $placeholder = $expected[++$i];

            if ('r' === $placeholder) {
                $end = mb_strpos($expected, '%r', $i + 1, '8bit');

                if (false !== $end) {
                    $regex .= str_replace('/', '\/', mb_substr($expected, $i + 1, $end - $i - 1, '8bit'));
                    $i = $end + 1;
                    continue;
                }
            }

            $regex .= match ($placeholder) {
                'e' => preg_quote(DIRECTORY_SEPARATOR, '/'),
                's' => '[^\r\n]+',
                'S' => '[^\r\n]*',
                'a' => '.+',
                'A' => '.*',
                'w' => '\s*',
                'i' => '[+-]?\d+',
                'd' => '\d+',
                'x' => '[0-9a-fA-F]+',
                'f' => '[+-]?\.?\d+\.?\d*(?:[Ee][+-]?\d+)?',
                'c' => '.',
                '0' => '\x00',
                default => preg_quote('%' . $placeholder, '/'),
            };
        }

        return $regex;
    }

    /** @return list<string> */
    private function lines(string $output): array
    {
        $lines = explode("\n", mb_rtrim(str_replace(["\r\n", "\r"], "\n", $output), "\n", '8bit'));

        return $lines === [''] ? [] : $lines;
    }

    /** @param list<string> $lines */
    private function join(array $lines): string
    {
        return implode("\n", $lines) . "\n";
    }
}
