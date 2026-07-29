--TEST--
Exception output: flavour_c1b891d8804619578af3455436cf979020f4bc7a
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
