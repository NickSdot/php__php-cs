--TEST--
Exception output: flavour_60f74ee73091a2753cdc571c00f9e9f1075262bb
--FILE--
<?php
try {
    throw new \ArgumentCountError('fixture message');
} catch (\ArgumentCountError $e) {
    echo 'ERROR 1: ', $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
ERROR 1: ArgumentCountError: fixture message
