--TEST--
Manual exception-output fixture for ext/phar/tests/032.phpt line 12
--EXTENSIONS--
phar
--FILE--
<?php
try {
    throw new PharException('phar "/tmp/032.phar.php" does not have a signature');
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
===DONE===
--EXPECTF--
phar "%s032.phar.php" does not have a signature===DONE===
