--TEST--
recvfromInto restores guard and Zend pins after typed peer exception
--EXTENSIONS--
swoole
swoole_buffer_test
--INI--
error_reporting=24575
--FILE--
<?php
$fatal = false; $typed = true;
require __DIR__ . '/recvfrom_into_peer.inc';
--EXPECT--
recvfromInto exception recovery OK
recvfromInto guard, pins, timeout and destruction OK
