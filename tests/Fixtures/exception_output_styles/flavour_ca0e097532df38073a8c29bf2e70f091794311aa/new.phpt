--TEST--
Exception output: flavour_ca0e097532df38073a8c29bf2e70f091794311aa
--FILE--
<?php
try {
    throw new \TypeError('fixture message');
} catch (\Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), ' in ', $e->getFile(), ' on line ', $e->getLine(), "\n";
}
?>
--EXPECTF--
TypeError: fixture message in %s on line %d
