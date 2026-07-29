--TEST--
Exception output: flavour_fb26ed52816224df45f9d48a1b9b5ad395492ba7
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Throwable $e) {
    echo 'ERROR: ', $e->getMessage();
}
?>
--EXPECTF--
ERROR: fixture message
