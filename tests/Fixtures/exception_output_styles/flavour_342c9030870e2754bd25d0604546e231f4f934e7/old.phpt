--TEST--
Exception output: flavour_342c9030870e2754bd25d0604546e231f4f934e7
--FILE--
<?php
try {
    throw new \ParseError('fixture message');
} catch (\ParseError $e) {
    echo "Parse error: {$e->getMessage()} on line {$e->getLine()}\n";
}
?>
--EXPECTF--
Parse error: fixture message on line %d
