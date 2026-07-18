<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle;

use InternalsCS\Fixer;
use InternalsCS\SourceFile;

use function array_slice;
use function array_unique;
use function array_values;
use function count;
use function implode;
use function mb_strlen;
use function mb_substr;
use function mb_substr_count;
use function mb_trim;
use function preg_match;
use function preg_split;
use function sort;
use function str_ends_with;
use function str_replace;
use function str_starts_with;

abstract class PhptFixer implements Fixer
{
    protected ?PhptFile $file = null;

    /** @var list<int> */
    private array $lines = [];

    private ?string $failureReason = null;

    private ?string $expectedUpdateFailure = null;

    final public function supports(SourceFile $file): bool
    {
        return str_ends_with($file->path, '.phpt');
    }

    final public function collect(SourceFile $file): bool
    {
        $this->file = new PhptFile($file->path, $file->rootDir);

        return $this->planPhptRewrite();
    }

    final public function persist(): bool
    {
        $file = $this->phptFile();

        if (!$this->hasPlannedRewrite()) {
            return $this->fail('internal error: collect() did not prepare a rewrite');
        }

        $initial = $file->run();
        if ('PASS' !== $initial['status']) {
            return $this->fail('original test did not pass (' . $this->runSummary($initial) . ')');
        }

        $this->apply();
        $file->save();

        $run = $file->run();
        if ('PASS' === $run['status']) {
            $file->cleanupArtifacts();
            return true;
        }

        if (!$this->changesOutput()) {
            return $this->fail('rewritten test did not pass after a style-only rewrite (' . $this->runSummary($run) . ')');
        }

        $actual = $file->readActualOutput();
        if (null === $actual) {
            return $this->fail('rewritten test did not pass and no .out file was produced (' . $this->runSummary($run) . ')');
        }

        $expectedSection = $file->expectedSectionName();
        if (null === $expectedSection) {
            return $this->fail('no expected output section is available for update');
        }

        $expected = $file->getSection($expectedSection);
        if (null === $expected) {
            return $this->fail("$expectedSection section disappeared while updating expected output");
        }

        $this->expectedUpdateFailure = null;
        $updated = $this->updateExpectedOutput($expectedSection, $expected, $actual);
        if (null === $updated) {
            return $this->fail(
                "$expectedSection update was not provable after rewritten test failed ("
                    . $this->runSummary($run) . '): '
                    . ($this->expectedUpdateFailure ?? 'actual output was not a safe expected-output rewrite')
            );
        }

        $file->setExpectedSection($expectedSection, $updated);
        $file->save();

        $verified = $file->run();
        if ('PASS' !== $verified['status']) {
            return $this->fail('updated expected output did not pass verification (' . $this->runSummary($verified) . ')');
        }

        $file->cleanupArtifacts();
        return true;
    }

    public function cleanup(): void
    {
        $this->file?->cleanupArtifacts();
    }

    public function location(): string
    {
        if ([] === $this->lines) {
            return '';
        }

        $visibleLines = array_slice($this->lines, 0, 5);
        $label = 1 === count($this->lines) ? 'FILE line ' : 'FILE lines ';
        $location = $label . implode(', ', $visibleLines);
        if (count($this->lines) > count($visibleLines)) {
            $location .= ' +' . (count($this->lines) - count($visibleLines)) . ' more';
        }

        return $location;
    }

    public function failureReason(): string
    {
        return $this->failureReason ?? 'unknown reason';
    }

    abstract protected function planPhptRewrite(): bool;

    abstract protected function apply(): void;

    abstract protected function hasPlannedRewrite(): bool;

    protected function changesOutput(): bool
    {
        return false;
    }

    protected function updateExpectedOutput(string $section, string $expected, string $actual): ?string
    {
        $this->setExpectedUpdateFailure("fixer does not support updating $section");
        return null;
    }

    protected function resetDiagnostics(): void
    {
        $this->lines = [];
        $this->failureReason = null;
        $this->expectedUpdateFailure = null;
    }

    protected function markLineForOffset(string $code, int $offset): void
    {
        $this->markLine(mb_substr_count(mb_substr($code, 0, $offset, '8bit'), "\n", '8bit') + 1);
    }

    protected function markLine(int $line): void
    {
        $this->lines[] = $line;
        sort($this->lines);
        $this->lines = array_values(array_unique($this->lines));
    }

    protected function setExpectedUpdateFailure(string $reason): void
    {
        $this->expectedUpdateFailure = $reason;
    }

    protected function fail(string $reason): bool
    {
        $this->failureReason = $reason;
        return false;
    }

    /** @param array{status: string, output: string, exitCode: int} $run */
    protected function runSummary(array $run): string
    {
        $status = $run['status'];
        if (1 === preg_match('/^SKIP .* reason: (.+)$/m', $run['output'], $matches)) {
            return $status . ': ' . $this->shorten(mb_trim($matches[1]));
        }
        if (1 === preg_match('/^(PASS|SKIP|FAIL|BORK|WARN|XFAIL|XLEAK|LEAK) .+$/m', $run['output'], $matches)) {
            return $this->shorten(mb_trim($matches[0]));
        }

        $lines = preg_split('/\R/', $run['output']);

        if (false === $lines) {
            $lines = [];
        }

        foreach ($lines as $line) {
            $line = mb_trim($line);
            if ('' !== $line && !str_starts_with($line, '=')) {
                return $status . ': ' . $this->shorten($line);
            }
        }

        return $status . ', exit code ' . $run['exitCode'];
    }

    protected function shorten(string $text): string
    {
        $text = str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], $text);
        if (mb_strlen($text) <= 180) {
            return $text;
        }

        return mb_substr($text, 0, 177) . '...';
    }

    protected function phptFile(): PhptFile
    {
        return $this->file ?? throw new \RuntimeException('PHPT fixer did not receive a source file');
    }
}
