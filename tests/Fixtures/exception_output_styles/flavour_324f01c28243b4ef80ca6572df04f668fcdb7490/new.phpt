--TEST--
Exception output: flavour_324f01c28243b4ef80ca6572df04f668fcdb7490
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
RuntimeException: fixture message
