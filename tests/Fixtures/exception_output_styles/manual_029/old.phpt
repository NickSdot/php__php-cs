--TEST--
Exception output: get_class() with message
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo get_class($e), ': ', $e->getMessage(), PHP_EOL;
}
?>
--EXPECT--
RuntimeException: fixture message
