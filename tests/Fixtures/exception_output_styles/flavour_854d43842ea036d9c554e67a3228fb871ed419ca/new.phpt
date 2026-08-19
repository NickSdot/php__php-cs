--TEST--
Exception output: flavour_854d43842ea036d9c554e67a3228fb871ed419ca
--FILE--
<?php
try {
    throw new \AssertionError('fixture message');
} catch (\Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
AssertionError: fixture message
