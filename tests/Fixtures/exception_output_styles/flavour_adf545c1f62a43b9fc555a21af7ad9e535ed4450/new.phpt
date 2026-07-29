--TEST--
Exception output: flavour_adf545c1f62a43b9fc555a21af7ad9e535ed4450
--FILE--
<?php
$type = 'fixture';
try {
    throw new \RuntimeException('fixture message');
} catch (\Throwable $e) {
    echo $type, '=>', $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
fixture=>RuntimeException: fixture message
