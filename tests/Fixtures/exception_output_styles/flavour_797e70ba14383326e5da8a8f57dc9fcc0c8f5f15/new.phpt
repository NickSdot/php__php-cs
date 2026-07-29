--TEST--
Exception output: flavour_797e70ba14383326e5da8a8f57dc9fcc0c8f5f15
--FILE--
<?php
try {
    throw new \ArgumentCountError('fixture message');
} catch (\ArgumentCountError $e) {
    echo $e::class, ': ', $e->getMessage(), PHP_EOL;
}
?>
--EXPECTF--
ArgumentCountError: fixture message
