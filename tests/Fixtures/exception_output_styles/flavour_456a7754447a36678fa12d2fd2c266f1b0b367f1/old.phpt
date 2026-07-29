--TEST--
Exception output: flavour_456a7754447a36678fa12d2fd2c266f1b0b367f1
--FILE--
<?php
try {
    throw new \TypeError('fixture message');
} catch (\TypeError|\ValueError $e) {
    echo get_class($e) . ': ' . $e->getMessage(), "\n";
}
?>
--EXPECTF--
TypeError: fixture message
