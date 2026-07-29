--TEST--
Exception output: flavour_854d43842ea036d9c554e67a3228fb871ed419ca
--FILE--
<?php
try {
    throw new \AssertionError('fixture message');
} catch (\AssertionError $e) {
    echo 'assert(): ', $e->getMessage(), ' failed', PHP_EOL;
}
?>
--EXPECTF--
assert(): fixture message failed
