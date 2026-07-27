--TEST--
Exception output: descriptive variable context
--FILE--
<?php
$ns_readable = '"fixture"';
$qname = 'name';

try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo "($ns_readable, \"$qname\"): ", $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECT--
("fixture", "name"): RuntimeException: fixture message
