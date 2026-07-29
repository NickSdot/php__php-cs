--TEST--
Exception output: flavour_4096a3e19fb9920923de2482786877b3786593b3
--FILE--
<?php
$msg = 'fixture';
try {
    throw new \RuntimeException('fixture message');
} catch (\Exception $e) {
    var_dump($e->getMessage(), $msg);
}
?>
--EXPECTF--
string(15) "fixture message"
string(7) "fixture"
