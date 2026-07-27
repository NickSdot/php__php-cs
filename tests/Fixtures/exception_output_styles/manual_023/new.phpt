--TEST--
Exception output: caught message with file and line
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), ' in ', $e->getFile(), ' on line ', $e->getLine(), PHP_EOL;
}
?>
--EXPECTF--
RuntimeException: fixture message in %s on line %d
