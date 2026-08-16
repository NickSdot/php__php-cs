--TEST--
Exception output: flavour_04ee7839622efd7b8fec1c82e4fd5ff980ef6f3d
--FILE--
<?php
try {
    throw new \Error('fixture message');
} catch (\Error $ex) {
    var_dump($ex->getMessage());
        echo "\n";
}
?>
--EXPECTF--
string(15) "fixture message"
