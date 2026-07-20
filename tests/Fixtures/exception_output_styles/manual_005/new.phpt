--TEST--
Manual exception-output fixture for ext/mysqli/tests/mysqli_execute_query_leak.phpt line 19
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo '[001] ', $e::class, ': ', $e->getMessage(), PHP_EOL;
}
?>
--EXPECT--
[001] RuntimeException: fixture message
