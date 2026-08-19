--TEST--
Exception output: flavour_342c9030870e2754bd25d0604546e231f4f934e7
--FILE--
<?php
try {
    throw new \ParseError('fixture message');
} catch (\Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), ' on line ', $e->getLine(), "\n";
}
?>
--EXPECTF--
ParseError: fixture message on line %d
