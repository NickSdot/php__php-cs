--TEST--
Exception output: flavour_3efaa6daaa4057cfc8f8111b8507223af372e41f
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
