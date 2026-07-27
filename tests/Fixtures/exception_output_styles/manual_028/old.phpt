--TEST--
Exception output: caught message with compact location
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo "Caught {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}\n";
}
?>
--EXPECTF--
Caught fixture message at %s:%d
