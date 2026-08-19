--TEST--
Exception output: flavour_8c6798e0dd2a1f040554bb1c26934d1780572ae1
--FILE--
<?php
try {
    throw new \TypeError('fixture message');
} catch (\Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
TypeError: fixture message
