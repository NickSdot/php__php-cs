--TEST--
Exception output: flavour_b568d42dae1958a53d8b0ad6028db48c773f3c4e
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Exception $e) {
    echo 'Caught ' . get_class($e) . '(' . $e->getMessage() . ")\n";
}
?>
--EXPECTF--
Caught RuntimeException(fixture message)
