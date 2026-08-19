--TEST--
Exception output: flavour_b568d42dae1958a53d8b0ad6028db48c773f3c4e
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
