--TEST--
Exception output: interpolated message with redundant label
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo "OK! {$e->getMessage()}";
}
?>
--EXPECT--
OK! fixture message
