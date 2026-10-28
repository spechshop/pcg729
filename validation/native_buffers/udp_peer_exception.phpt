--TEST--
recvfromInto releases pending receive after recoverable peer destructor exception
--EXTENSIONS--
swoole
swoole_buffer_test
--FILE--
<?php require __DIR__.'/peer_exception.php'; ?>
--EXPECT--
peer exception cleanup PASS
