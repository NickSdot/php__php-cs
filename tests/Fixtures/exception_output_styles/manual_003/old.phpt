--TEST--
Manual exception-output fixture for ext/mysqli/tests/mysqli_execute_query.phpt line 67
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo '[010] '.$e->getMessage()."\n";
}
?>
--EXPECT--
[010] fixture message
