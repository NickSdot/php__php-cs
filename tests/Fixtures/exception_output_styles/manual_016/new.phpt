--TEST--
Manual exception-output fixture for ext/pdo_mysql/tests/bug68371.phpt line 61
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
