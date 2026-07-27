--TEST--
Exception output: redundant exception-class prefix
--FILE--
<?php
try {
    throw new LogicException('fixture message');
} catch (LogicException $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECT--
LogicException: fixture message
