--TEST--
Exception output: message-only printf error labels
--FILE--
<?php
try {
    throw new TypeError('first fixture message');
} catch (TypeError $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}

try {
    throw new ArgumentCountError('second fixture message');
} catch (ArgumentCountError $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}


try {
    throw new ValueError('third fixture message');
} catch (ValueError $e) {
    echo $e::class, ': ', $e->getMessage(), PHP_EOL;
}
?>
--EXPECT--
TypeError: first fixture message
ArgumentCountError: second fixture message
ValueError: third fixture message
