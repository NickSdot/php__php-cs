--TEST--
Exception output: concatenated get_class() and message
--FILE--
<?php
try {
    throw new TypeError('fixture message');
} catch (\TypeError|\ValueError $e) {
    echo get_class($e) . ': ' . $e->getMessage(), "\n";
}
?>
--EXPECT--
TypeError: fixture message
