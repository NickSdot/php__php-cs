--TEST--
Exception output: flavour_a5c6e0980a10c8d1c17a949c1ed0cbaf36ed77a6
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Throwable $e) {
    echo $e::class, $e->getMessage(), "\n";
}
?>
--EXPECTF--
RuntimeExceptionfixture message
