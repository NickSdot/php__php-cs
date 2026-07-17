--TEST--
Manual exception-output fixture for ext/soap/tests/bugs/protocol_relative_redirect.phpt line 45
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
