--TEST--
Manual exception-output fixture for ext/pdo/tests/pecl_bug_5217.phpt line 23
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), \PHP_EOL;
}
?>
--EXPECT--
RuntimeException: fixture message
