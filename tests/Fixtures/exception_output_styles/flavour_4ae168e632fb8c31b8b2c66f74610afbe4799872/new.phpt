--TEST--
Exception output: flavour_4ae168e632fb8c31b8b2c66f74610afbe4799872
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Exception $e) {
    echo $e::class, ': ', $e->getMessage(), ' on line ', $e->getLine(), "\n";
}
?>
--EXPECTF--
RuntimeException: fixture message on line %d
