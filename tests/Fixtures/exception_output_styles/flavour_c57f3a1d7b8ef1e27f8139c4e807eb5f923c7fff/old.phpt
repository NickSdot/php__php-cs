--TEST--
Exception output: flavour_c57f3a1d7b8ef1e27f8139c4e807eb5f923c7fff
--FILE--
<?php
try {
    throw new \ArgumentCountError('fixture message');
} catch (\ArgumentCountError $e) {
    print('Error found: '.$e->getMessage());
}
echo "\nfollowing output\n";
?>
--EXPECTF--
Error found: fixture message
following output
