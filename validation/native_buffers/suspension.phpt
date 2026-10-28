--TEST--
Nested borrows block Fiber suspension and defer automatic coroutine preemption
--EXTENSIONS--
swoole
swoole_buffer_test
--FILE--
<?php require __DIR__.'/suspension.inc'; ?>
--EXPECT--
Fiber suspend, nested guards, automatic preemption deferral and scheduler recovery PASS
