--TEST--
Exception output: flavour_b8eae420f3e3088aaea885a3c86696a72e336467
--FILE--
<?php
try {
    throw new \TypeError('fixture message');
} catch (\TypeError $e) {
    echo "\n", $e->getMessage(), "\n";
}
?>
--EXPECTF--

fixture message
