--TEST--
Exception output: flavour_1e1f9f2a7cbf5735bada7b65b3ef3ec7a1bacec1
--FILE--
<?php
try {
    throw new \Error('fixture message');
} catch (\Error $ex) {
    echo "{$ex->getCode()}: {$ex->getMessage()} - {$ex->getFile()}({$ex->getLine()})\n\n";
}
?>
--EXPECTF--
%d: fixture message - %s(%d)

