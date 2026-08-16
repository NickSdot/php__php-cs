--TEST--
Exception output: flavour_2cbbbe0b58fbdf66db63ab8624915c088aa33133
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Exception $e) {
    print $e->getMessage();
    print_r($e->getTrace());
}
?>
--EXPECTF--
fixture messageArray
(
)
