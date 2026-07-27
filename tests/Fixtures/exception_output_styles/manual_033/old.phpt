--TEST--
Exception output: redundant exception-class prefix
--FILE--
<?php
try {
    throw new LogicException('fixture message');
} catch (LogicException $e) {
    echo "LogicException: ".$e->getMessage()."\n";
}
?>
--EXPECT--
LogicException: fixture message
