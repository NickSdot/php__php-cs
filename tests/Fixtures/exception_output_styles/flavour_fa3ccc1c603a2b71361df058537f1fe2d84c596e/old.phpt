--TEST--
Exception output: flavour_fa3ccc1c603a2b71361df058537f1fe2d84c596e
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Exception $e) {
    echo 'Caught exception with message "', $e->getMessage(), '"', "\n";
}
?>
--EXPECTF--
Caught exception with message "fixture message"
