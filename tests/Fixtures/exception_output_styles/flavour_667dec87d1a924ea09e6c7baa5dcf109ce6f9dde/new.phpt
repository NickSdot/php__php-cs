--TEST--
Exception output: flavour_667dec87d1a924ea09e6c7baa5dcf109ce6f9dde
--FILE--
<?php
try {
    throw new \PDOException('fixture message');
} catch (\Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
PDOException: fixture message
