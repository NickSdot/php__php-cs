--TEST--
Manual exception-output fixture for ext/mysqli/tests/mysqli_stmt_execute_bind.phpt line 80
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo '[006] ', $e::class, ': ', $e->getMessage(), \PHP_EOL;
}
?>
--EXPECT--
[006] RuntimeException: fixture message
