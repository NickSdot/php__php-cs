--TEST--
Exception output: redundant exception-class label
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo "SoapFault: " . $e->getMessage() . "\n";
}
?>
--EXPECT--
SoapFault: fixture message
