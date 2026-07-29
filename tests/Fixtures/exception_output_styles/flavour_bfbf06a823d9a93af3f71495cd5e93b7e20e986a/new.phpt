--TEST--
Exception output: flavour_bfbf06a823d9a93af3f71495cd5e93b7e20e986a
--FILE--
<?php
try {
    throw new \TypeError('fixture message');
} catch (\TypeError $e) {
    echo $e::class, ': ', $e->getMessage(), PHP_EOL;
}
?>
--EXPECTF--
TypeError: fixture message
