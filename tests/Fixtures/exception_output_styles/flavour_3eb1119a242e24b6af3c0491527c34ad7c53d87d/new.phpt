--TEST--
Exception output: flavour_3eb1119a242e24b6af3c0491527c34ad7c53d87d
--FILE--
<?php
try {
    throw new \ValueError('fixture message');
} catch (\Throwable $e) {
    echo "\n", $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
ValueError: fixture message
