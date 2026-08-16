--TEST--
Exception output: flavour_8225115fe81c4e3c54e7101f303e5820240e2182
--FILE--
<?php
echo 'preceding output', "\n";
try {
    throw new \ValueError('fixture message');
} catch (\ValueError $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
preceding output
ValueError: fixture message
