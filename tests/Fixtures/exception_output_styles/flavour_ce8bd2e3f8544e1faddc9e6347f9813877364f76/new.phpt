--TEST--
Exception output: flavour_ce8bd2e3f8544e1faddc9e6347f9813877364f76
--FILE--
<?php
$i = 'fixture';
try {
    throw new \RuntimeException('fixture message');
} catch (\Exception $e) {
    echo "Case $i: ", $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
Case fixture: RuntimeException: fixture message
