--TEST--
Exception output: concatenated newline after message
--FILE--
<?php
try {
    throw new RuntimeException('first fixture message');
} catch (Throwable $exception) {
    echo $exception::class, ': ', $exception->getMessage(), "\n";
}

try {
    throw new RuntimeException('second fixture message');
} catch (Throwable $exception) {
    echo $exception::class, ': ', $exception->getMessage(), "\n";
}
?>
--EXPECT--
RuntimeException: first fixture message
RuntimeException: second fixture message
