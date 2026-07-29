--TEST--
Exception output: flavour_d21d9e5bc45c0b4ddcee8f923dfb32dd02633325
--FILE--
<?php
try {
    throw new \DOMException('fixture message');
} catch (\DOMException $e) {
    echo $e->getCode(), ": ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
%d: fixture message
