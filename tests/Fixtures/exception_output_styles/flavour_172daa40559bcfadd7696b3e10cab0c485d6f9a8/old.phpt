--TEST--
Exception output: flavour_172daa40559bcfadd7696b3e10cab0c485d6f9a8
--FILE--
<?php
$name = 'fixture';
try {
    throw new \SoapFault('fixture fault', 'fixture message');
} catch (\SoapFault $e) {
    echo "$name: {$e->getMessage()}\n";
}
?>
--EXPECTF--
fixture: fixture message
