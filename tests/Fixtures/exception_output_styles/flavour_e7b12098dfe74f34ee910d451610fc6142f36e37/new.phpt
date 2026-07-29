--TEST--
Exception output: flavour_e7b12098dfe74f34ee910d451610fc6142f36e37
--FILE--
<?php
try {
    throw new \AssertionError('fixture message');
} catch (\AssertionError $e) {
    echo $e::class, ': ', $e->getMessage(), PHP_EOL;
}
?>
--EXPECTF--
AssertionError: fixture message
