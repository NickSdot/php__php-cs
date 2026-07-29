--TEST--
Exception output: flavour_d23b0fba67949d8fa8c06dfe6ab202a15dda33b1
--FILE--
<?php
try {
    throw new \ValueError('fixture message');
} catch (\ValueError $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
ValueError: fixture message
