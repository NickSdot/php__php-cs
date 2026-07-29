--TEST--
Exception output: flavour_ada1de7976792b1fe997ebd5f8c41fff5bf62d61
--FILE--
<?php
try {
    throw new \Error('fixture message');
} catch (\Error $e) {
    echo $e->getMessage(), " in ", $e->getFile(), " on line ", $e->getLine();
}
?>
--EXPECTF--
fixture message in %s on line %d
