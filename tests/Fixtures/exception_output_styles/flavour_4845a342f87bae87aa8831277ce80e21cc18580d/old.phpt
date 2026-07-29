--TEST--
Exception output: flavour_4845a342f87bae87aa8831277ce80e21cc18580d
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Exception $e) {
    print "in catch: ".$e->getMessage()."\n";
}
?>
--EXPECTF--
in catch: fixture message
