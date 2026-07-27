--TEST--
Exception output: message-only printf error labels
--FILE--
<?php
try {
    throw new TypeError('first fixture message');
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

try {
    throw new ArgumentCountError('second fixture message');
} catch (ArgumentCountError $e) {
    print('Error found: '.$e->getMessage());
}
echo "\n";

try {
    throw new ValueError('third fixture message');
} catch (ValueError $e) {
    echo $e->getMessage();
}
?>
--EXPECT--
first fixture message
Error found: second fixture message
third fixture message
