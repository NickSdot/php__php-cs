--TEST--
Exception output: flavour_71aee91c0dc4ea6819a7c6951486bd03748bb438
--FILE--
<?php
try {
    throw new \AssertionError('');
} catch (\Throwable $e) {
    echo $e::class, ': "', $e->getMessage(), '"', "\n";
}
?>
--EXPECTF--
AssertionError: ""
