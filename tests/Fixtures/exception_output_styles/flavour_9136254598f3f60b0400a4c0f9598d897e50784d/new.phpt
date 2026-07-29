--TEST--
Exception output: flavour_9136254598f3f60b0400a4c0f9598d897e50784d
--FILE--
<?php
try {
    throw new \ArgumentCountError('fixture message');
} catch (\ArgumentCountError $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
ArgumentCountError: fixture message
