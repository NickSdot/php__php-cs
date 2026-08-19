--TEST--
Exception output: flavour_86dc8a5548cf8988f4094e99e830d6da21f637ac
--FILE--
<?php
try {
    throw new \Error('fixture message');
} catch (\Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), ' on line ', $e->getLine(), "\n";
}
?>
--EXPECTF--
Error: fixture message on line %d
