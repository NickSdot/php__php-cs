--TEST--
Exception output: flavour_1e1f9f2a7cbf5735bada7b65b3ef3ec7a1bacec1
--FILE--
<?php
try {
    throw new \Error('fixture message');
} catch (\Throwable $ex) {
    echo $ex::class, ': ', $ex->getCode(), ': ', $ex->getMessage(), ' in ', $ex->getFile(), ' on line ', $ex->getLine(), "\n";
}
?>
--EXPECTF--
Error: %d: fixture message in %s on line %d
