--TEST--
Exception output: flavour_92c8cb8ddd21518499d3cc6affd9306e0a0ebb7d
--FILE--
<?php
try {
    throw new \ErrorException('fixture message');
} catch (\Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
ErrorException: fixture message
