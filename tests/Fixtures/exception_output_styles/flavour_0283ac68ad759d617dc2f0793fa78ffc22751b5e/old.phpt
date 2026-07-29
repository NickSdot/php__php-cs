--TEST--
Exception output: flavour_0283ac68ad759d617dc2f0793fa78ffc22751b5e
--FILE--
<?php
try {
    throw new \ReflectionException('fixture message');
} catch (\ReflectionException $e) {
    echo "Expected exception for class-based reflection: " . $e->getMessage() . "\n";
}
?>
--EXPECTF--
Expected exception for class-based reflection: fixture message
