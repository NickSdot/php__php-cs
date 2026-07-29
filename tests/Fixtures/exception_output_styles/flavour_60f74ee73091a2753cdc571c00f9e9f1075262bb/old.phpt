--TEST--
Exception output: flavour_60f74ee73091a2753cdc571c00f9e9f1075262bb
--FILE--
<?php
try {
    throw new \ArgumentCountError('fixture message');
} catch (\ArgumentCountError $e) {
    var_dump('ERROR 1', $e->getMessage());
}
?>
--EXPECTF--
string(7) "ERROR 1"
string(15) "fixture message"
