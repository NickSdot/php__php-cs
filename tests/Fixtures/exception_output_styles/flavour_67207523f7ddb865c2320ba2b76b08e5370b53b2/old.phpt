--TEST--
Exception output: flavour_67207523f7ddb865c2320ba2b76b08e5370b53b2
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
}
?>
--EXPECTF--
Exception: fixture message in %s on line %d
