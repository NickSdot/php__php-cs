--TEST--
Bug #73629 (SplDoublyLinkedList::setIteratorMode masks intern flags)
--FILE--
<?php
$q = new SplQueue();
try {
    $q->setIteratorMode(SplDoublyLinkedList::IT_MODE_FIFO);
} catch (Exception $e) {
    echo $e::class, ': ', $e->getMessage(), \PHP_EOL;
}
try {
    $q->setIteratorMode(SplDoublyLinkedList::IT_MODE_LIFO);
} catch (Exception $e) {
    echo 'expected exception: ' . $e::class . ': ' . $e->getMessage() . \PHP_EOL;
}
?>
--EXPECT--
expected exception: RuntimeException: Iterators' LIFO/FIFO modes for SplStack/SplQueue objects are frozen
