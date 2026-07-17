--TEST--
Manual exception-output fixture for ext/mysqli/tests/mysqli_stmt_execute_bind.phpt line 71
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo '[005] ', $e::class, ': ', $e->getMessage(), \PHP_EOL;
}
?>
--EXPECT--
[005] RuntimeException: fixture message
