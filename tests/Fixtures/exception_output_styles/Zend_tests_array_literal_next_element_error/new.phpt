--TEST--
Next free element may overflow in array literals
--FILE--
<?php

$i = PHP_INT_MAX;
try {
    $array = [$i => 42, new stdClass];
    var_dump($array);
} catch (Error $e) {
    echo $e::class, ': ', $e->getMessage(), \PHP_EOL;
}

function test($x = [PHP_INT_MAX => 42, "foo"]) {}
try {
    test();
} catch (Error $e) {
    echo $e::class, ': ', $e->getMessage(), \PHP_EOL;
}

?>
--EXPECT--
Error: Cannot add element to the array as the next element is already occupied
Error: Cannot add element to the array as the next element is already occupied
