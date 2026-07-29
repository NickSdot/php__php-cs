--TEST--
Exception output: flavour_917721e06a2fe4cecf227c85dcda51a36884ef64
--FILE--
<?php
$rf = new class { public function getName(): string { return 'fixture'; } };
try {
    throw new \Error('fixture message');
} catch (\Error $e) {
    echo "{$rf->getName()}: {$e->getMessage()}\n";
}
?>
--EXPECTF--
fixture: fixture message
