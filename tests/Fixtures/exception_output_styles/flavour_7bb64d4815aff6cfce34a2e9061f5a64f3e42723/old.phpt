--TEST--
Exception output: flavour_7bb64d4815aff6cfce34a2e9061f5a64f3e42723
--FILE--
<?php
try {
    throw new \TypeError('fixture message');
} catch (\TypeError $e) {
    echo $e->getMessage() . "(" . $e->getLine() .  ")\n";
}
?>
--EXPECTF--
fixture message(%d)
