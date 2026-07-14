--TEST--
Pretty printing for arrow functions
--INI--
zend.assertions=1
--FILE--
<?php

try {
    assert((fn() => false)());
} catch (AssertionError $e) {
    echo 'assert(): ', $e::class . ': ' . $e->getMessage(), ' failed', \PHP_EOL;
}

try {
    assert((fn&(int... $args): ?bool => $args[0])(false));
} catch (AssertionError $e) {
    echo 'assert(): ', $e::class . ': ' . $e->getMessage(), ' failed', \PHP_EOL;
}

?>
--EXPECT--
assert(): AssertionError: assert((fn() => false)()) failed
assert(): AssertionError: assert((fn&(int ...$args): ?bool => $args[0])(false)) failed
