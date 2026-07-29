--TEST--
Exception output: flavour_c4d7a27cef33bb906f04afad457132110f780562
--FILE--
<?php
try {
    throw new \Error('fixture message');
} catch (\Error $e) {
    echo "Error: {$e->getMessage()}\n";
}
?>
--EXPECTF--
Error: fixture message
