--TEST--
Exception output: flavour_aeb9efaa3e176aa5b64e36f1eb70f11c8b8ae6c9
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
