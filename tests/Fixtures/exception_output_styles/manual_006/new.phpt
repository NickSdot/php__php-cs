--TEST--
Manual exception-output fixture for ext/mysqli/tests/mysqli_execute_query_leak.phpt line 25
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo '[002] ', $e::class, ': ', $e->getMessage(), \PHP_EOL;
}
?>
--EXPECT--
[002] RuntimeException: fixture message
