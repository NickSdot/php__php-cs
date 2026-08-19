--TEST--
Exception output: flavour_7bb64d4815aff6cfce34a2e9061f5a64f3e42723
--FILE--
<?php
try {
    throw new \TypeError('fixture message');
} catch (\Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), ' on line ', $e->getLine(), "\n";
}
?>
--EXPECTF--
TypeError: fixture message on line %d
