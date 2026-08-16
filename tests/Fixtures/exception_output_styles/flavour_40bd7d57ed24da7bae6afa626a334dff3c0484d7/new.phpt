--TEST--
Exception output: flavour_40bd7d57ed24da7bae6afa626a334dff3c0484d7
--FILE--
<?php
try {
    throw new \UnhandledMatchError('fixture message');
} catch (\UnhandledMatchError $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
UnhandledMatchError: fixture message
