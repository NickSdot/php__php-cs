--TEST--
Exception output: flavour_8c714c23601b0bca1e6b3c2f47edc9c49e2565f3
--FILE--
<?php
$label = 'fixture';
try {
    throw new \ValueError('fixture message');
} catch (\ValueError $e) {
    echo $label, ': ', $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
fixture: ValueError: fixture message
