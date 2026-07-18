<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle;

use InternalsCS\Support\ProcessEnvironment;

use function array_any;
use function array_find;
use function array_last;
use function count;
use function dirname;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function getenv;
use function is_array;
use function is_executable;
use function is_file;
use function is_int;
use function is_resource;
use function is_string;
use function mb_strlen;
use function mb_substr;
use function pathinfo;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function proc_close;
use function proc_open;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function stream_get_contents;
use function unlink;

// todo: too many hard-coded inline arrays and strings that should be class constants
final class PhptFile
{
    private string $prefix = '';
    /** @var array<int, array{name: string, header: string, content: string}> */
    private array $sections = [];

    public function __construct(private readonly string $path, private readonly string $rootDir)
    {
        $contents = file_get_contents($this->path);
        if (false === $contents) {
            throw new \RuntimeException("Cannot read {$this->path}");
        }
        $this->parse($contents);
    }

    public function relativePath(): string
    {
        if (str_starts_with($this->path, $this->rootDir . DIRECTORY_SEPARATOR)) {
            return mb_substr($this->path, mb_strlen($this->rootDir, '8bit') + 1, null, '8bit');
        }
        return $this->path;
    }

    public function hasSection(string $name): bool
    {
        return array_any($this->sections, fn($section) => $section['name'] === $name);
    }

    public function getSection(string $name): ?string
    {
        foreach ($this->sections as $section) {
            if ($section['name'] === $name) {
                return $section['content'];
            }
        }
        return null;
    }

    public function setSection(string $name, string $content): void
    {
        foreach ($this->sections as $i => $section) {
            if ($section['name'] === $name) {
                $this->sections[$i]['content'] = $content;
                return;
            }
        }
        throw new \RuntimeException("Section $name not found in {$this->path}");
    }

    public function renameSection(string $oldName, string $newName): void
    {
        foreach ($this->sections as $i => $section) {
            if ($section['name'] === $oldName) {
                $lineEnding = str_contains($section['header'], "\r\n") ? "\r\n" : "\n";
                $this->sections[$i]['name'] = $newName;
                $this->sections[$i]['header'] = "--$newName--$lineEnding";
                return;
            }
        }
        throw new \RuntimeException("Section $oldName not found in {$this->path}");
    }

    public function codeSectionName(): ?string
    {
        if ($this->hasSection('FILE')) {
            return 'FILE';
        }
        if ($this->hasSection('FILEEOF')) {
            return 'FILEEOF';
        }
        return null;
    }

    public function expectedSectionName(): ?string
    {
        return array_find([ 'EXPECT', 'EXPECTF', 'EXPECTREGEX' ], fn($name) => $this->hasSection($name));
    }

    public function setExpectedOutput(string $output): void
    {
        $section = $this->expectedSectionName();
        if (null === $section) {
            throw new \RuntimeException("No expected output section in {$this->path}");
        }

        $finalLineEndingSuffix = $this->finalLineEndingSuffix($this->contents());
        $expectedSectionIsLast = $this->isLastSection($section);

        if ('EXPECT' !== $section) {
            $this->renameSection($section, 'EXPECT');
        }

        $output = str_replace(["\r\n", "\r"], "\n", $output);

        $this->setSection('EXPECT', $this->expectedSectionContent(
            output: $output,
            sectionIsLast: $expectedSectionIsLast,
            finalLineEndingSuffix: $finalLineEndingSuffix,
        ));
    }

    public function setExpectedSection(string $section, string $output): void
    {
        if (!$this->hasSection($section)) {
            throw new \RuntimeException("Section $section not found in {$this->path}");
        }

        $this->setSection($section, $this->expectedSectionContent(
            output: str_replace(["\r\n", "\r"], "\n", $output),
            sectionIsLast: $this->isLastSection($section),
            finalLineEndingSuffix: $this->finalLineEndingSuffix($this->contents()),
        ));
    }

    private function expectedSectionContent(string $output, bool $sectionIsLast, string $finalLineEndingSuffix): string
    {
        if ($sectionIsLast) {
            return $this->withFinalLineEndingSuffix($output, $finalLineEndingSuffix);
        }

        if ('' !== $output && !str_ends_with($output, "\n")) {
            $output .= "\n";
        }

        return $output;
    }

    public function save(): void
    {
        if (false === file_put_contents($this->path, $this->render())) {
            throw new \RuntimeException("Cannot write {$this->path}");
        }
    }

    public function contents(): string
    {
        return $this->render();
    }

    public function replaceContents(string $contents): void
    {
        $this->parse($contents);
    }

    private function isLastSection(string $name): bool
    {
        $last = array_last($this->sections) ?? null;

        return null !== $last && $last['name'] === $name;
    }

    private function finalLineEndingSuffix(string $contents): string
    {
        if (1 !== preg_match('/(?:\r\n|\n|\r)*\z/', $contents, $matches)) {
            throw new \RuntimeException("Cannot read final line ending suffix in {$this->path}");
        }

        return $matches[0];
    }

    private function withFinalLineEndingSuffix(string $contents, string $suffix): string
    {
        $rewritten = preg_replace('/(?:\r\n|\n|\r)+\z/', '', $contents);

        if (null === $rewritten) {
            throw new \RuntimeException("Cannot write final line ending suffix in {$this->path}");
        }

        return $rewritten . $suffix;
    }

    /** @return array{status: string, output: string, exitCode: int} */
    public function run(): array
    {
        $this->cleanupArtifacts();

        $phpBinary = $this->testPhpBinary();
        $cmd = [
            $phpBinary,
            'run-tests.php',
            '-q',
            '--no-progress',
            '--no-color',
            $this->relativePath(),
        ];
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $env = new ProcessEnvironment()->variables();
        $env['NO_INTERACTION'] = '1';
        $env['REPORT_EXIT_STATUS'] = '1';
        $env['TEST_PHP_EXECUTABLE'] = $phpBinary;

        $cgiBinary = $this->testPhpCgiBinary();

        if (null !== $cgiBinary) {
            $env['TEST_PHP_CGI_EXECUTABLE'] = $cgiBinary;
        }

        $process = proc_open($cmd, $descriptorSpec, $pipes, $this->rootDir, $env);
        if (!is_resource($process)) {
            throw new \RuntimeException("Cannot run {$this->relativePath()}");
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $output = $stdout . $stderr;
        $status = 'unknown';
        if (1 === preg_match('/^(PASS|SKIP|FAIL|BORK|WARN|XFAIL|XLEAK|LEAK) /m', $output, $matches)) {
            $status = $matches[1];
        }

        return [
            'status' => $status,
            'output' => $output,
            'exitCode' => $exitCode,
        ];
    }

    public function readActualOutput(): ?string
    {
        $path = $this->artifactBase() . '.out';
        if (!is_file($path)) {
            return null;
        }
        $contents = file_get_contents($path);
        return false === $contents ? null : $contents;
    }

    public function cleanupArtifacts(): void
    {
        $base = $this->artifactBase();
        foreach (['diff', 'exp', 'log', 'mem', 'out', 'php', 'sh', 'skip.php', 'clean.php'] as $extension) {
            $path = "$base.$extension";
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function parse(string $contents): void
    {
        $this->sections = [];
        $matched = preg_match_all('/^--([_A-Z]+)--[ \t]*(?:\r\n|\n|\r|$)/m', $contents, $matches, PREG_OFFSET_CAPTURE);

        if (false === $matched || 0 === $matched) {
            $this->prefix = $contents;
            return;
        }

        $this->prefix = mb_substr($contents, 0, $this->matchOffset($matches[0][0]), '8bit');
        $count = count($matches[0]);

        for ($i = 0; $i < $count; $i++) {
            $header = $this->matchText($matches[0][$i]);
            $name = $this->matchText($matches[1][$i]);
            $start = $this->matchOffset($matches[0][$i]);
            $nextStart = $i + 1 < $count ? $this->matchOffset($matches[0][$i + 1]) : null;
            $contentStart = $start + mb_strlen($header, '8bit');
            $contentEnd = $nextStart ?? mb_strlen($contents, '8bit');
            $this->sections[] = [
                'name' => $name,
                'header' => $header,
                'content' => mb_substr($contents, $contentStart, $contentEnd - $contentStart, '8bit'),
            ];
        }
    }

    private function matchText(mixed $match): string
    {
        if (!is_array($match)) {
            throw new \RuntimeException("Invalid PHPT section match in {$this->path}");
        }

        $text = $match[0] ?? null;

        if (is_string($text)) {
            return $text;
        }

        throw new \RuntimeException("Invalid PHPT section match in {$this->path}");
    }

    private function matchOffset(mixed $match): int
    {
        if (!is_array($match)) {
            throw new \RuntimeException("Invalid PHPT section offset in {$this->path}");
        }

        $offset = $match[1] ?? null;

        if (is_int($offset)) {
            return $offset;
        }

        throw new \RuntimeException("Invalid PHPT section offset in {$this->path}");
    }

    private function render(): string
    {
        $contents = $this->prefix;
        foreach ($this->sections as $section) {
            $contents .= $section['header'] . $section['content'];
        }
        return $contents;
    }

    private function artifactBase(): string
    {
        $pathInfo = pathinfo($this->path);
        return $pathInfo['dirname'] . DIRECTORY_SEPARATOR . $pathInfo['filename'];
    }

    private function testPhpBinary(): string
    {
        $configured = getenv('INTERNALS_CS_TEST_PHP_EXECUTABLE');

        if (is_string($configured) && is_file($configured) && is_executable($configured)) {
            return $configured;
        }

        $local = $this->toolRoot() . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'php-test-runtime' . DIRECTORY_SEPARATOR . 'php';

        if (is_file($local) && is_executable($local)) {
            return $local;
        }

        $binary = $this->rootDir . DIRECTORY_SEPARATOR . 'sapi' . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR . 'php';
        if (is_file($binary) && is_executable($binary)) {
            return $binary;
        }

        return PHP_BINARY;
    }

    private function testPhpCgiBinary(): ?string
    {
        $configured = getenv('INTERNALS_CS_TEST_PHP_CGI_EXECUTABLE');

        if (is_string($configured) && is_file($configured) && is_executable($configured)) {
            return $configured;
        }

        $local = $this->toolRoot() . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'php-test-runtime' . DIRECTORY_SEPARATOR . 'php-cgi';

        if (is_file($local) && is_executable($local)) {
            return $local;
        }

        $binary = $this->rootDir . DIRECTORY_SEPARATOR . 'sapi' . DIRECTORY_SEPARATOR . 'cgi' . DIRECTORY_SEPARATOR . 'php-cgi';

        if (is_file($binary) && is_executable($binary)) {
            return $binary;
        }

        return null;
    }

    private function toolRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
