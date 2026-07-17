--TEST--
Manual exception-output fixture for ext/pdo/tests/pecl_bug_5217.phpt line 23
--FILE--
<?php
try {
    throw new RuntimeException('fixture message');
} catch (Throwable $e) {
    echo "Safely caught " . $e->getMessage() . "\n";
}
?>
--EXPECT--
Safely caught fixture message
