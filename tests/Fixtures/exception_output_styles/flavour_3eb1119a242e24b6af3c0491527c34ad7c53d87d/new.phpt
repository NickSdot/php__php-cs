--TEST--
Exception output: flavour_3eb1119a242e24b6af3c0491527c34ad7c53d87d
--FILE--
<?php
try {
    throw new \ValueError('fixture message');
} catch (\ValueError $e) {
    echo PHP_EOL, $e::class, ': ', $e->getMessage(), PHP_EOL;
}
?>
--EXPECTF--
ValueError: fixture message
