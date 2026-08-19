--TEST--
Exception output: flavour_d21d9e5bc45c0b4ddcee8f923dfb32dd02633325
--FILE--
<?php
try {
    throw new \DOMException('fixture message');
} catch (\Throwable $e) {
    echo $e::class, ': ', $e->getCode(), ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
DOMException: %d: fixture message
