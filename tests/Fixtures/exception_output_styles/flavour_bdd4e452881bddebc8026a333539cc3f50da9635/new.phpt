--TEST--
Exception output: flavour_bdd4e452881bddebc8026a333539cc3f50da9635
--FILE--
<?php
try {
    throw new \TypeError('fixture message');
} catch (\Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
TypeError: fixture message
