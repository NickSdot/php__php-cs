--TEST--
Exception output: printed message with redundant catch label
--FILE--
<?php
try {
    throw new UnexpectedValueException('fixture message');
} catch (Exception $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECT--
UnexpectedValueException: fixture message
