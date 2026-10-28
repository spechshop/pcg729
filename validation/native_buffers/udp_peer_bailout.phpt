--TEST--
recvfromInto must release pending receive and references after peer destructor bailout
--EXTENSIONS--
swoole
swoole_buffer_test
--INI--
display_errors=1
log_errors=0
error_reporting=24575
--FILE--
<?php require __DIR__.'/peer_bailout.php'; ?>
--EXPECTF--
%AFatal error: isolated peer destructor bailout in %s on line %d
%Apeer bailout guard released
destroyed=yes
