--TEST--
Exception output: flavour_4b2c87902bf89baa94ce35c173b47fe23af2cfa2
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Exception $e) {
    echo 'Compare: ', $e::class, ': ', $e->getMessage(), PHP_EOL;
}
?>
--EXPECTF--
Compare: RuntimeException: fixture message
