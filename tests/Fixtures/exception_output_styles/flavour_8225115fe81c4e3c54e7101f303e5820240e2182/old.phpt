--TEST--
Exception output: flavour_8225115fe81c4e3c54e7101f303e5820240e2182
--FILE--
<?php
echo 'preceding output';
try {
    throw new \ValueError('fixture message');
} catch (\ValueError $e) {
    echo \PHP_EOL . $e->getMessage() . \PHP_EOL;
}
?>
--EXPECTF--
preceding output
fixture message
