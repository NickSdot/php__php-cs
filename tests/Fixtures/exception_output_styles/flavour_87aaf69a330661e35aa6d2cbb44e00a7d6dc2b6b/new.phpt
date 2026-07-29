--TEST--
Exception output: flavour_87aaf69a330661e35aa6d2cbb44e00a7d6dc2b6b
--FILE--
<?php
try {
    throw new \Error('fixture message');
} catch (\Error $e) {
    echo $e::class, ': ', $e->getMessage(), PHP_EOL;
}
?>
--EXPECTF--
Error: fixture message
