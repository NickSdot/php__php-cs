--TEST--
Manual exception-output fixture for ext/pdo_firebird/tests/gh17383.phpt line 25
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
