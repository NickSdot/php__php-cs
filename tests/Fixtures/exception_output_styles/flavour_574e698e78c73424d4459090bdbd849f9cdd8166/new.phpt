--TEST--
Exception output: flavour_574e698e78c73424d4459090bdbd849f9cdd8166
--FILE--
<?php
try {
    throw new \Error('fixture message');
} catch (\Error $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
Error: fixture message
