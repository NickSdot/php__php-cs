--TEST--
Exception output: flavour_25f5385ee50e62d6ad7d0a6692a7bbf6860c76c6
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Throwable $e) {
    echo $e->getMessage() . "\n";
}
?>
--EXPECTF--
fixture message
