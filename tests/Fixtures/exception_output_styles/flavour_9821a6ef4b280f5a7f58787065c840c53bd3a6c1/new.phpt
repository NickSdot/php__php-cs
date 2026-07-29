--TEST--
Exception output: flavour_9821a6ef4b280f5a7f58787065c840c53bd3a6c1
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
