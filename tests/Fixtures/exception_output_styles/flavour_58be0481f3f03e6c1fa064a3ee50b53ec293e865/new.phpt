--TEST--
Exception output: flavour_58be0481f3f03e6c1fa064a3ee50b53ec293e865
--FILE--
<?php
try {
    throw new \RuntimeException('fixture message');
} catch (\Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
RuntimeException: fixture message
