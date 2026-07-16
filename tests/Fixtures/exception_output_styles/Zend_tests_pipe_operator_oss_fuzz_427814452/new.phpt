--TEST--
OSS-Fuzz #427814452
--FILE--
<?php

try {
    false |> assert(...);
} catch (\AssertionError $e) {
    echo $e::class, ': ', $e->getMessage(), \PHP_EOL;
}
try {
    0 |> "assert"(...);
} catch (\AssertionError $e) {
    echo $e::class, ': ', $e->getMessage(), \PHP_EOL;
}
try {
    false |> ("a"."ssert")(...);
} catch (\AssertionError $e) {
    echo $e::class, ': ', $e->getMessage(), \PHP_EOL;
}

?>
--EXPECT--
AssertionError: 
AssertionError: 
AssertionError:
