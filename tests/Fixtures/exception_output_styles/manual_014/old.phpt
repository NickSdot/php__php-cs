--TEST--
Manual exception-output fixture for ext/pdo_firebird/tests/bug_77863.phpt line 113
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo $e->getMessage() . '<br>';
    echo "\n";
}
?>
--EXPECT--
fixture message<br>
