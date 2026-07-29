--TEST--
Exception output: flavour_5a1dceb974f946aee80936a0c39a182d9da15130
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
