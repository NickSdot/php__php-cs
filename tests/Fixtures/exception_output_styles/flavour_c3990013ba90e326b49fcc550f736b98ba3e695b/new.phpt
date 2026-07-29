--TEST--
Exception output: flavour_c3990013ba90e326b49fcc550f736b98ba3e695b
--FILE--
<?php
try {
    throw new \TypeError('fixture message');
} catch (\TypeError $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
TypeError: fixture message
