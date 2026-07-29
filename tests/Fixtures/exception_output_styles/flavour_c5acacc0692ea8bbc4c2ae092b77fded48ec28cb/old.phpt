--TEST--
Exception output: flavour_c5acacc0692ea8bbc4c2ae092b77fded48ec28cb
--FILE--
<?php
try {
    throw new \AssertionError('fixture message');
} catch (\AssertionError $e) {
    echo "Assertion failure: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
Assertion failure: fixture message
