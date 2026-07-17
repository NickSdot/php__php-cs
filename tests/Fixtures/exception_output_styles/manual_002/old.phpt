--TEST--
Manual exception-output fixture for ext/mysqli/tests/mysqli_execute_query.phpt line 62
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo '[009] '.$e->getMessage()."\n";
}
?>
--EXPECT--
[009] fixture message
