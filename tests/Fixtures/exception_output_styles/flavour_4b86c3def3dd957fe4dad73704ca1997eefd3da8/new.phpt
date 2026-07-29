--TEST--
Exception output: flavour_4b86c3def3dd957fe4dad73704ca1997eefd3da8
--FILE--
<?php
try {
    throw new \Error('fixture message');
} catch (\Error $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
Error: fixture message
