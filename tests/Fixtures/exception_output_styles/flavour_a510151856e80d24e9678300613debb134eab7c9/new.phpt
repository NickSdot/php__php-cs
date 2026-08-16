--TEST--
Exception output: class-message trace output is not rewritten
--FILE--
<?php
$algo = 'fixture';
$serial = 'serialized';

try {
    echo "Done\n";
} catch (Throwable $e) {
    echo "$algo: problem with serialization {$serial}\n";
    echo '  ', $e::class . ': ' . $e->getMessage(), "\n", $e->getTraceAsString();
}
?>
--EXPECT--
Done
