--TEST--
Exception output: redundant exception-type label
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
