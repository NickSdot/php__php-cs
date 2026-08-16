--TEST--
Exception output: flavour_affbb59ba04d6175bcc9c2a1b8e072596c128475
--FILE--
<?php
echo 'preceding output';
try {
    var_dump(throw new \TypeError('fixture message'));
} catch (\TypeError $e) {
    echo "\n", $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
preceding output
TypeError: fixture message
