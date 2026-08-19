--TEST--
Exception output: flavour_1a808a63d856229b6b8d91e8de33819a9cdba8f2
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
