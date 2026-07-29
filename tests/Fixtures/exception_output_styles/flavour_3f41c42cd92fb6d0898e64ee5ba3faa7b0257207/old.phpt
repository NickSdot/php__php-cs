--TEST--
Exception output: flavour_3f41c42cd92fb6d0898e64ee5ba3faa7b0257207
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Exception $e) {
    var_dump($e->getMessage());
}
?>
following inline output
--EXPECT--
string(15) "fixture message"
following inline output
