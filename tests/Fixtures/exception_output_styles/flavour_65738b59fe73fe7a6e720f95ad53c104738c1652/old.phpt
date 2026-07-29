--TEST--
Exception output: flavour_65738b59fe73fe7a6e720f95ad53c104738c1652
--FILE--
<?php
try {
    throw new \TypeError('fixture message');
} catch (\TypeError $e) {
    echo get_class($e) . ': ' . $e->getMessage() . \PHP_EOL;
}
?>
--EXPECTF--
TypeError: fixture message
