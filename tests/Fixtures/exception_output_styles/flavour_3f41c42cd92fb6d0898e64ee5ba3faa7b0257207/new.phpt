--TEST--
Exception output: flavour_3f41c42cd92fb6d0898e64ee5ba3faa7b0257207
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
following inline output
--EXPECT--
RuntimeException: fixture message
following inline output
