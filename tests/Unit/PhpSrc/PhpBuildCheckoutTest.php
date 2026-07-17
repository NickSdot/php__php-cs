<?php

declare(strict_types=1);

namespace Tests\Unit\PhpSrc;

use InternalsCS\Console\ConsoleIo;
use InternalsCS\PhpSrc\PhpBuildCheckout;
use InternalsCS\PhpSrc\PhpBuildPaths;
use InternalsCS\PhpSrc\PhpSrcRoot;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function proc_close;
use function proc_open;
use function random_bytes;
use function stream_get_contents;
use function sys_get_temp_dir;

final class PhpBuildCheckoutTest extends TestCase
{
    public function testPreparesRuntimeCheckoutFromLocalMasterWithoutTouchingDirtyCurrentBranch(): void
    {
        $root = $this->makeTempDir();
        $source = $root . '/source';
        $runtime = new PhpBuildPaths($root . '/runtime');

        mkdir($source);
        $this->git($source, 'init', '-b', 'master');
        $this->git($source, 'config', 'user.email', 'internals-cs@example.test');
        $this->git($source, 'config', 'user.name', 'Internals CS');
        file_put_contents($source . '/run-tests.php', "<?php\n// master\n");
        $this->git($source, 'add', 'run-tests.php');
        $this->git($source, 'commit', '-m', 'Initial master');
        $this->git($source, 'checkout', '-b', 'PHP-8.5');
        file_put_contents($source . '/run-tests.php', "<?php\n// dirty branch\n");

        $checkout = new PhpBuildCheckout()->prepare(
            sourceRoot: PhpSrcRoot::fromPath($source),
            paths: $runtime,
            io: new NullConsoleIo(),
        );

        self::assertSame("<?php\n// master\n", file_get_contents($checkout->path . '/run-tests.php'));
        self::assertSame("<?php\n// dirty branch\n", file_get_contents($source . '/run-tests.php'));
    }

    private function makeTempDir(): string
    {
        $path = sys_get_temp_dir() . '/internals-cs-build-checkout-' . bin2hex(random_bytes(6));
        mkdir($path);

        return $path;
    }

    private function git(string $cwd, string ...$args): void
    {
        $command = ['git'];

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
            $cwd,
        );

        self::assertIsResource($process);

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        self::assertSame(0, proc_close($process), $stderr);
    }
}

final class NullConsoleIo implements ConsoleIo
{
    public function out(string $message): void {}

    public function err(string $message): void {}
}
