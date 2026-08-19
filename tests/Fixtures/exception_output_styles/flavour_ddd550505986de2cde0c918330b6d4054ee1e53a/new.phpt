--TEST--
Exception output: flavour_ddd550505986de2cde0c918330b6d4054ee1e53a
--FILE--
<?php
try {
    throw new \ValueError('fixture message');
} catch (\Throwable $exception) {
    echo $exception::class, ': ', $exception->getMessage(), "\n";
}
?>
--EXPECTF--
ValueError: fixture message
