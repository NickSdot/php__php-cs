--TEST--
Exception output: flavour_c5acacc0692ea8bbc4c2ae092b77fded48ec28cb
--FILE--
<?php
try {
    throw new \AssertionError('fixture message');
} catch (\Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
AssertionError: fixture message
