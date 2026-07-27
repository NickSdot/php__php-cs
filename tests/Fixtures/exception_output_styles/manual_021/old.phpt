--TEST--
Exception output: message with file and line
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo "\nException: " . $e->getMessage() . " in " , $e->getFile() . " on line " . $e->getLine() . "\n";
}
?>
--EXPECTF--

Exception: fixture message in %s on line %d
