--TEST--
Exception output: flavour_95387a6ac60f92228db5dbacfc936c8f73c46238
--FILE--
<?php
try {
    throw new \Error('fixture message');
} catch (\Error $e) {
    echo get_class($e) . ': ' . $e->getCode() . ', ' . $e->getMessage() . \PHP_EOL;
}
?>
--EXPECTF--
Error: 0, fixture message
