--TEST--
Exception output: flavour_275668ed4272e611e9440fe0c75b766252665176
--FILE--
<?php
$property = 'fixture';
try {
    throw new \ReflectionException('fixture message');
} catch (\ReflectionException $e) {
    echo "Property $property from class: EXCEPTION - " . $e->getMessage() . "\n\n";
}
?>
--EXPECTF--
Property fixture from class: EXCEPTION - fixture message

