--TEST--
Exception output: printed message with redundant catch label
--FILE--
<?php
try {
    throw new UnexpectedValueException('fixture message');
} catch (Exception $e) {
    print "in catch: ".$e->getMessage()."\n";
}
?>
--EXPECT--
in catch: fixture message
