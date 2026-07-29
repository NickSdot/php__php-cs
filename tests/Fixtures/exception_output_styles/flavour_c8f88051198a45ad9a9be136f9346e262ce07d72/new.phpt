--TEST--
Exception output: flavour_c8f88051198a45ad9a9be136f9346e262ce07d72
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
