--TEST--
Exception output: flavour_1a808a63d856229b6b8d91e8de33819a9cdba8f2
--FILE--
<?php
try {
    throw new \Error('fixture message');
} catch (\Error $e) {
    echo "\nException: " . $e->getMessage() . " in " , $e->getFile() . " on line " . $e->getLine() . "\n";
}
?>
--EXPECTF--

Exception: fixture message in %s on line %d
