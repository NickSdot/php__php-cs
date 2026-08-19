--TEST--
Exception output: flavour_0283ac68ad759d617dc2f0793fa78ffc22751b5e
--FILE--
<?php
try {
    throw new \ReflectionException('fixture message');
} catch (\Throwable $e) {
    echo 'class-based reflection: ', $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
class-based reflection: ReflectionException: fixture message
