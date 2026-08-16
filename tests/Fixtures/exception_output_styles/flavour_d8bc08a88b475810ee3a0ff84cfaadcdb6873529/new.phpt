--TEST--
Exception output: flavour_d8bc08a88b475810ee3a0ff84cfaadcdb6873529
--FILE--
<?php
try {
    throw new \Error('fixture message');
} catch (\Error $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
echo "following output\n";
?>
--EXPECTF--
Error: fixture message
following output
