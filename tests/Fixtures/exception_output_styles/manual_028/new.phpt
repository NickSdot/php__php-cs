--TEST--
Exception output: caught message with compact location
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), ' in ', $e->getFile(), ' on line ', $e->getLine(), "\n";
}
?>
--EXPECTF--
RuntimeException: fixture message in %s on line %d
