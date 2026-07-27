--TEST--
Exception output: class and message with descriptive context
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo 'saveXml: ', $e::class, ': ', $e->getMessage(), "\n";
}

try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo 'innerHTML: ', $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECT--
saveXml: RuntimeException: fixture message
innerHTML: RuntimeException: fixture message
