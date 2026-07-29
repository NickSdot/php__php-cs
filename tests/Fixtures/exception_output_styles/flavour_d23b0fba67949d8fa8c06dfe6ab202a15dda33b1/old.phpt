--TEST--
Exception output: flavour_d23b0fba67949d8fa8c06dfe6ab202a15dda33b1
--FILE--
<?php
try {
    throw new \ValueError('fixture message');
} catch (\ValueError $e) {
    print('Error found: '.$e->getMessage().".\n");
}
?>
--EXPECTF--
Error found: fixture message.
