--TEST--
Exception output: flavour_4d03c051d0fad9d7bc2b8aecf9d8dd4c9f251900
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Exception $e) {
    var_dump(get_class($e));
        echo $e->getMessage() . "\n";
}
?>
--EXPECTF--
string(16) "RuntimeException"
fixture message
