--TEST--
Exception output: message followed by inline output
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
===DONE===
--EXPECTF--
fixture message===DONE===
