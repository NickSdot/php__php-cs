--TEST--
Exception output: flavour_3b948714f3ccb19b83baca973120e79724b2dfe6
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
