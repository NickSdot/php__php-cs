--TEST--
Exception output: flavour_d5af624599d24aeda9a790ef48757ae16e57cf98
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\RuntimeException $e) {
    echo "Caught {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}\n";
}
?>
--EXPECTF--
Caught fixture message at %s:%d
