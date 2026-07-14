--TEST--
ReflectionExtension::__construct()
--CREDITS--
Gerrit "Remi" te Sligte <remi@wolerized.com>
Leon Luijkx <leon@phpgg.nl>
--FILE--
<?php
try {
    $obj = new ReflectionExtension();
} catch (TypeError $re) {
    echo "Ok - ".$re::class . ": " . $re->getMessage().PHP_EOL;
}

try {
    $obj = new ReflectionExtension('foo', 'bar');
} catch (TypeError $re) {
    echo "Ok - ".$re::class . ": " . $re->getMessage().PHP_EOL;
}

try {
    $obj = new ReflectionExtension([]);
} catch (TypeError $re) {
    echo "Ok - ".$re::class . ": " . $re->getMessage().PHP_EOL;
}


?>
--EXPECT--
Ok - ArgumentCountError: ReflectionExtension::__construct() expects exactly 1 argument, 0 given
Ok - ArgumentCountError: ReflectionExtension::__construct() expects exactly 1 argument, 2 given
Ok - TypeError: ReflectionExtension::__construct(): Argument #1 ($name) must be of type string, array given
