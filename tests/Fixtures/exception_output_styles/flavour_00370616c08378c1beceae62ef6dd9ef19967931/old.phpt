--TEST--
Exception output: flavour_00370616c08378c1beceae62ef6dd9ef19967931
--FILE--
<?php
try {
    throw new \Error('fixture message');
} catch (\Error $e) {
    echo $e::class, ': ', $e->getCode(), ': ', $e->getMessage(), PHP_EOL;
}
?>
--EXPECTF--
Error: 0: fixture message
