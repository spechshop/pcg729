--TEST--
recvfromInto releases guard, both Zend pins and unpublished peer on bailout
--EXTENSIONS--
swoole
swoole_buffer_test
--INI--
display_errors=1
log_errors=0
html_errors=0
fatal_error_backtraces=1
zend.exception_ignore_args=0
error_reporting=24575
--FILE--
<?php
$typed = false; $fatal = true;
require __DIR__ . '/recvfrom_into_peer.inc';
--EXPECTF--
%AFatal error: recvfromInto peer bailout in %s on line %d
%ArecvfromInto guard, pins and timeout OK; fatal frame refs match PHP control
