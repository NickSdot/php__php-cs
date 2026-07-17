--TEST--
Manual exception-output fixture for ext/pdo_firebird/tests/gh17383.phpt line 25
--FILE--
<?php
try {
    throw new PDOException('fixture message', 335544721);
} catch (PDOException $e) {
    echo 'PDOException code: ' . $e->getCode() . "\n";
    echo $e::class, ': ', $e->getMessage(), \PHP_EOL;
}
?>
--EXPECT--
PDOException code: 335544721
PDOException: fixture message
