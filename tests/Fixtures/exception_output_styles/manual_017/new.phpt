--TEST--
Manual exception-output fixture for ext/soap/tests/bugs/protocol_relative_redirect.phpt line 45
--FILE--
<?php
if (!class_exists('SoapFault')) {
    class SoapFault extends RuntimeException {}
}

function fixtureSoapFault(): SoapFault {
    if ((new ReflectionClass(SoapFault::class))->isInternal()) {
        return new SoapFault('Client', 'fixture message');
    }

    return new SoapFault('fixture message');
}

try {
    throw fixtureSoapFault();
} catch (SoapFault $e) {
    echo $e::class, ': ', $e->getMessage(), \PHP_EOL;
}
?>
--EXPECT--
SoapFault: fixture message
