--TEST--
Manual exception-output fixture for ext/mysqli/tests/mysqli_stmt_execute_bind.phpt line 107
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo '[007] ', $e::class, ': ', $e->getMessage(), \PHP_EOL;
}
?>
--EXPECT--
[007] RuntimeException: fixture message
