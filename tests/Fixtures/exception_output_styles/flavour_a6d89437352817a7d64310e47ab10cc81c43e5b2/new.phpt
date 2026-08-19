--TEST--
Exception output: flavour_a6d89437352817a7d64310e47ab10cc81c43e5b2
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Throwable $e) {
    echo 'bool: ', $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
bool: RuntimeException: fixture message
