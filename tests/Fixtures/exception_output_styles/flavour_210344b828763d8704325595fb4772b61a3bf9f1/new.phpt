--TEST--
Exception output: flavour_210344b828763d8704325595fb4772b61a3bf9f1
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Throwable $ex) {
    echo $ex::class, ': ', $ex->getMessage(), "\n";
}
?>
--EXPECTF--
RuntimeException: fixture message
