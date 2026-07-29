--TEST--
Exception output: flavour_284de0e7bd5d92fd77ef8059475e29433baca179
--FILE--
<?php
try {
    throw new \ParseError('fixture message');
} catch (\ParseError $e) {
    echo $e->getMessage(), " on line ", $e->getLine(), "\n";
}
?>
--EXPECTF--
fixture message on line %d
