--TEST--
Exception output: flavour_b68139d59e76dd23f468dc35c33b32717e4c002b
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
