--TEST--
Exception output: message followed by inline output
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Exception $e) {
    echo $e::class, ': ', $e->getMessage(), PHP_EOL;
}
?>
===DONE===
--EXPECTF--
RuntimeException: fixture message
===DONE===
