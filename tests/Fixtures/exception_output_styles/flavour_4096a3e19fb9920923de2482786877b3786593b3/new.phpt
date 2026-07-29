--TEST--
Exception output: flavour_4096a3e19fb9920923de2482786877b3786593b3
--FILE--
<?php
$msg = 'fixture';
try {
    throw new \RuntimeException('fixture message');
} catch (\Exception $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
    var_dump($msg);
}
?>
--EXPECTF--
RuntimeException: fixture message
string(7) "fixture"
