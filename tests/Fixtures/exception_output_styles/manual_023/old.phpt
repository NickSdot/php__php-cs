--TEST--
Exception output: caught message with file and line
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo "*** Caught ", $e->getMessage(), " in ", $e->getFile(), " on line ", $e->getLine(), PHP_EOL;
}
?>
--EXPECTF--
*** Caught fixture message in %s on line %d
