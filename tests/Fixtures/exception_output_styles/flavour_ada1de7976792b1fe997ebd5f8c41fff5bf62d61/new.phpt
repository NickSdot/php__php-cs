--TEST--
Exception output: flavour_ada1de7976792b1fe997ebd5f8c41fff5bf62d61
--FILE--
<?php
try {
    throw new \Error('fixture message');
} catch (\Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), ' in ', $e->getFile(), ' on line ', $e->getLine(), "\n";
}
?>
--EXPECTF--
Error: fixture message in %s on line %d
