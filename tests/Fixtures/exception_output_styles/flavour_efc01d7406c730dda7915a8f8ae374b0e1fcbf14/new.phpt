--TEST--
Exception output: flavour_efc01d7406c730dda7915a8f8ae374b0e1fcbf14
--FILE--
<?php
$ns_readable = 'fixture';
$qname = 'fixture';
try {
    throw new \DOMException('fixture message');
} catch (\DOMException $e) {
    echo "($ns_readable, \"$qname\"): ", $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
(fixture, "fixture"): DOMException: fixture message
