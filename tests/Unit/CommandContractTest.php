<?php

declare(strict_types=1);

namespace Tests\Unit;

use InternalsCS\Command\GenerateCommand;
use InternalsCS\Command\GenerateOptions;
use InternalsCS\Command\GenerateTarget;
use InternalsCS\Console\Application;
use InternalsCS\Console\ConsoleIo;
use InternalsCS\Fixture\FixtureGenerationResult;
use InternalsCS\Fixture\FixtureGenerator;
use InternalsCS\Fixture\FixtureRewriteRunner;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function fclose;
use function file_put_contents;
use function is_resource;
use function mkdir;
use function proc_close;
use function proc_open;
use function random_bytes;
use function stream_get_contents;
use function sys_get_temp_dir;

final class CommandContractTest extends TestCase
{
    public function testFixRequiresExplicitPhpSrcDirectory(): void
    {
        $io = new BufferConsoleIo();

        self::assertSame(2, new Application($io)->run(['php-cs.php', 'fix']));
        self::assertSame("--php-src-dir /path/to/php-src is required\n", $io->stderr);
    }

    public function testGenerateRequiresExplicitPhpSrcDirectory(): void
    {
        $io = new BufferConsoleIo();

        self::assertSame(2, new Application($io)->run(['php-cs.php', 'generate']));
        self::assertSame("--php-src-dir /path/to/php-src is required\n", $io->stderr);
    }

    public function testCommandHelpReturnsSuccess(): void
    {
        $io = new BufferConsoleIo();
        $app = new Application($io);

        self::assertSame(0, $app->run(['php-cs.php', 'fix', '--help']));
        self::assertSame(0, $app->run(['php-cs.php', 'generate', '--help']));
        self::assertStringContainsString('Usage: php bin/php-cs.php fix', $io->stdout);
        self::assertStringContainsString('Usage: php bin/php-cs.php generate', $io->stdout);
        self::assertStringContainsString('--force-php-binary-rebuild', $io->stdout);
    }

    public function testGenerateRejectsDirtySourceBeforeRuntimeWork(): void
    {
        $source = $this->tempDir();
        $this->git($source, 'init');
        file_put_contents($source . '/run-tests.php', "<?php\n");

        $io = new BufferConsoleIo();
        $target = new DirtyGuardGenerateTarget();

        self::assertSame(1, new GenerateCommand([$target])->run(
            script: 'php-cs.php generate',
            args: ['--php-src-dir', $source, '--write'],
            io: $io,
        ));
        self::assertSame("source checkout is dirty; pass --allow-dirty to generate anyway\n", $io->stderr);
    }

    private function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/php-cs-command-' . bin2hex(random_bytes(6));
        mkdir($dir);

        return $dir;
    }

    private function git(string $dir, string ...$args): void
    {
        $command = ['git', '-C', $dir];

        foreach ($args as $arg) {
            $command[] = $arg;
        }

        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        self::assertTrue(is_resource($process));

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        self::assertSame(0, proc_close($process));
    }
}

final class BufferConsoleIo implements ConsoleIo
{
    public string $stdout = '';

    public string $stderr = '';

    public function out(string $message): void
    {
        $this->stdout .= $message;
    }

    public function err(string $message): void
    {
        $this->stderr .= $message;
    }
}

final readonly class DirtyGuardGenerateTarget implements GenerateTarget
{
    public function name(): string
    {
        return 'dirty-guard';
    }

    public function description(): string
    {
        return 'Test dirty guard';
    }

    public function sourceExtensions(): array
    {
        return ['phpt'];
    }

    public function defaultFixturesDir(string $toolRoot): string
    {
        return $toolRoot;
    }

    public function defaultReportsDir(string $toolRoot): string
    {
        return $toolRoot;
    }

    public function checkRuntime(ConsoleIo $io): bool
    {
        return true;
    }

    public function requiresPhpTestRuntime(): bool
    {
        return true;
    }

    public function generator(): FixtureGenerator
    {
        throw new \RuntimeException('Generator should not run for a dirty source checkout');
    }

    public function rewriteRunner(GenerateOptions $options): FixtureRewriteRunner
    {
        throw new \RuntimeException('Rewrite runner should not be created for a dirty source checkout');
    }

    public function printResult(FixtureGenerationResult $result, ConsoleIo $io): int
    {
        throw new \RuntimeException('Result should not be printed for a dirty source checkout');
    }
}
