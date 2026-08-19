--TEST--
Exception output: flavour_fa3ccc1c603a2b71361df058537f1fe2d84c596e
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
RuntimeException: fixture message
