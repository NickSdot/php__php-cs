--TEST--
Exception output: flavour_3ea91c9f0995baa5574002aa86bcf891e536e7af
--FILE--
<?php
try {
    throw new \Error('fixture message');
} catch (\Throwable $e) {
    echo 'saveXml: ', $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
saveXml: Error: fixture message
