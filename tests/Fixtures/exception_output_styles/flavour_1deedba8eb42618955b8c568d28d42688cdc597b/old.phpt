--TEST--
Exception output: flavour_1deedba8eb42618955b8c568d28d42688cdc597b
--FILE--
<?php
$option = 'fixture';
try {
    throw new \ValueError('fixture message');
} catch (\ValueError $exception) {
    echo $option . ": " . $exception->getMessage() . "\n\n";
}
?>
--EXPECTF--
fixture: fixture message

