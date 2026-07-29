--TEST--
Exception output: flavour_45fdc1f0fde14c56078dba327f5a0a9c09cf3315
--FILE--
<?php
try {
    throw new \Error('fixture message');
} catch (\Error $e) {
    var_dump($e->getCode(), $e->getMessage());
}
?>
--EXPECTF--
int(0)
string(15) "fixture message"
