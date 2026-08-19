--TEST--
Exception output: flavour_b8eae420f3e3088aaea885a3c86696a72e336467
--FILE--
<?php
try {
    throw new \TypeError('fixture message');
} catch (\Throwable $e) {
    echo "\n", $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
TypeError: fixture message
