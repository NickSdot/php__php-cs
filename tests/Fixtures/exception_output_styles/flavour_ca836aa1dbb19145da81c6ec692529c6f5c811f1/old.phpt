--TEST--
Exception output: flavour_ca836aa1dbb19145da81c6ec692529c6f5c811f1
--FILE--
<?php
try {
    throw new \ValueError('fixture message');
} catch (\ValueError $e) {
    echo '[009] '.$e->getMessage()."\n";
}
?>
--EXPECTF--
[009] fixture message
