--TEST--
Exception output: flavour_d86eb3a9ad9ea1d0d96a5d8936daac5634f6c74d
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
