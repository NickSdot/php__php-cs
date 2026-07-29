--TEST--
Exception output: flavour_4d03c051d0fad9d7bc2b8aecf9d8dd4c9f251900
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Exception $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
RuntimeException: fixture message
