--TEST--
Exception output: flavour_108dcb084479582eea0a4b0a351df0f406c76a86
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Exception $e) {
    echo "{$e->getMessage()}\n";
}
?>
--EXPECTF--
fixture message
