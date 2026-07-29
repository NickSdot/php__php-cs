--TEST--
Exception output: flavour_d6f35901fb83986153a2373de0f0b9a24071e8f8
--FILE--
<?php
try {
    throw new \Error('fixture message');
} catch (\Error $e) {
    echo  get_class($e), ': ', $e->getMessage(), \PHP_EOL;
}
?>
--EXPECTF--
Error: fixture message
