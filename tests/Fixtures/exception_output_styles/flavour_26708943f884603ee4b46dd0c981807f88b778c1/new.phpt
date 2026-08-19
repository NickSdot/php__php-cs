--TEST--
Exception output: flavour_26708943f884603ee4b46dd0c981807f88b778c1
--FILE--
<?php
if (!class_exists('com_exception')) { class com_exception extends \RuntimeException {} }
try {
    throw new \com_exception('fixture message');
} catch (\Throwable $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
com_exception: fixture message
