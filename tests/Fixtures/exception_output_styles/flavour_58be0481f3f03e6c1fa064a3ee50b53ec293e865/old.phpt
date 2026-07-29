--TEST--
Exception output: flavour_58be0481f3f03e6c1fa064a3ee50b53ec293e865
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Exception $e) {
    print $e->getMessage();
}
?>
--EXPECTF--
fixture message
