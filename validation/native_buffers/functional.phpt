--TEST--
UDP required datagram sizes, 10000 reuse, peer identity, overflow, conflicting buffer, timeout and cancellation
--EXTENSIONS--
sockets
swoole
--FILE--
<?php require __DIR__.'/functional.inc'; ?>
--EXPECTF--
{"sizes":[0,1,12,160,1024,2048],"oversized":[2049,4096,65507],"reuse":10000,"scheduler_ticks":%d,"cancel_code":125,"status":"PASS"}
