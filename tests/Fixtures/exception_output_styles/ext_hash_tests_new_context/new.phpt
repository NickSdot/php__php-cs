--TEST--
Hash: Attempt to instantiate a HashContext directly
--FILE--
<?php

try {
  new HashContext;
} catch (Error $e) {
  echo $e::class, ': ', $e->getMessage(), PHP_EOL;
}
?>
--EXPECT--
Error: Call to private HashContext::__construct() from global scope
