--TEST--
Exception output: flavour_54219ec39fa4c64ee920477dd1fda26a70cc54b8
--FILE--
<?php
try {
    throw new \mysqli_sql_exception('fixture message');
} catch (\mysqli_sql_exception $e) {
    echo $e->getMessage();
        echo "\n";
}
?>
--EXPECTF--
fixture message
