--TEST--
Exception output: flavour_aeb9efaa3e176aa5b64e36f1eb70f11c8b8ae6c9
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Exception $e) {
    echo "Caught in " . $e->getMessage() . "()\n";
}
?>
--EXPECTF--
Caught in fixture message()
