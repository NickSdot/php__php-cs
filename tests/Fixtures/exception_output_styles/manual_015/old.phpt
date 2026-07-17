--TEST--
Manual exception-output fixture for ext/pdo_firebird/tests/gh17383.phpt line 25
--FILE--
<?php
try {
    throw new PDOException('fixture message', 335544721);
} catch (PDOException $e) {
    echo 'PDOException code: ' . $e->getCode() . "\n";
    echo 'PDOException message: ' . $e->getMessage() . "\n";
}
?>
--EXPECT--
PDOException code: 335544721
PDOException message: fixture message
