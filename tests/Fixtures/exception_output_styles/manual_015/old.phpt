--TEST--
Manual exception-output fixture for ext/pdo_firebird/tests/gh17383.phpt line 25
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo 'PDOException message: ' . $e->getMessage() . "\n";
}
?>
--EXPECT--
PDOException message: fixture message
