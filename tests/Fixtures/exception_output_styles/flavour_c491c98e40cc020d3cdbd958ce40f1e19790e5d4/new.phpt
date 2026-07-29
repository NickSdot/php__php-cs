--TEST--
Exception output: flavour_c491c98e40cc020d3cdbd958ce40f1e19790e5d4
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
RuntimeException: fixture message
