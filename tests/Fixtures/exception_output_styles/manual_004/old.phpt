--TEST--
Manual exception-output fixture for ext/mysqli/tests/mysqli_execute_query.phpt line 82
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo '[012] '.$e->getMessage()."\n";
}
?>
--EXPECT--
[012] fixture message
