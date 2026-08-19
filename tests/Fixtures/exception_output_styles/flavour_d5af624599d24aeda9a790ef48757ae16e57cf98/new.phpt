--TEST--
Exception output: flavour_d5af624599d24aeda9a790ef48757ae16e57cf98
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), ' in ', $e->getFile(), ' on line ', $e->getLine(), "\n";
}
?>
--EXPECTF--
RuntimeException: fixture message in %s on line %d
