--TEST--
Exception output: descriptive context with get_class()
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $t) {
    echo "Wrong exception type thrown: ".get_class($t)." : ".$t->getMessage()."\n";
}
?>
--EXPECT--
Wrong exception type thrown: RuntimeException : fixture message
