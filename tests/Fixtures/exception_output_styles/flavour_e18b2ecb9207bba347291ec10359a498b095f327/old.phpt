--TEST--
Exception output: flavour_e18b2ecb9207bba347291ec10359a498b095f327
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Throwable $e) {
    echo $e::class, ": ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
RuntimeException: fixture message
