--TEST--
Exception output: flavour_45fdc1f0fde14c56078dba327f5a0a9c09cf3315
--FILE--
<?php
try {
    throw new \Error('fixture message');
} catch (\Error $e) {
    echo $e::class, ': ', $e->getCode(), ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
Error: 0: fixture message
