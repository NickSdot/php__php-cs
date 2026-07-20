--TEST--
Manual exception-output fixture for ext/ffi/tests/031.phpt line 16
--FILE--
<?php
$type = 'manual_type';

try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo $type, '=>', $e::class, ': ', $e->getMessage(), PHP_EOL;
}
?>
--EXPECT--
manual_type=>RuntimeException: fixture message
