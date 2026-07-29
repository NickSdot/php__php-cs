--TEST--
Exception output: flavour_d058d782d0e439acaa7285934e2ba739c1de5e3f
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Throwable $exception) {
    echo $exception->getMessage();
}
?>
following inline output
--EXPECT--
fixture messagefollowing inline output
