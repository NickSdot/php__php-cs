--TEST--
Exception output: flavour_e9bdf1133e602b2c7c69ef720f895bf2248a4fa9
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Exception $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
following inline output
--EXPECTF--
RuntimeException: fixture message
following inline output
