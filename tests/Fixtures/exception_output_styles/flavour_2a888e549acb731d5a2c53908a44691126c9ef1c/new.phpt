--TEST--
Exception output: flavour_2a888e549acb731d5a2c53908a44691126c9ef1c
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
