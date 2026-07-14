--TEST--
ZE2 catch exception thrown in destructor
--FILE--
<?php

class FailClass
{
    public $fatal;

    function __destruct()
    {
        echo __METHOD__ . "\n";
        throw new exception("FailClass");
        echo "Done: " . __METHOD__ . "\n";
    }
}

try
{
    $a = new FailClass;
    unset($a);
}
catch(Exception $e)
{
    echo "Caught: " . $e::class . ": " . $e->getMessage() . PHP_EOL;
}

class FatalException extends Exception
{
    function __construct($what)
    {
        echo __METHOD__ . "\n";
        $o = new FailClass;
        unset($o);
        echo "Done: " . __METHOD__ . "\n";
    }
}

try
{
    throw new FatalException("Damn");
}
catch(Exception $e)
{
    echo "Caught Exception: " . $e::class . ": " . $e->getMessage() . PHP_EOL;
}
catch(FatalException $e)
{
    echo "Caught FatalException: " . $e::class . ": " . $e->getMessage() . PHP_EOL;
}

?>
--EXPECT--
FailClass::__destruct
Caught: Exception: FailClass
FatalException::__construct
FailClass::__destruct
Caught Exception: Exception: FailClass
