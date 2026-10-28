--TEST--
Concurrent reads on one socket reject with the native Swoole fatal and exit without SIGSEGV
--EXTENSIONS--
swoole
--INI--
display_errors=1
log_errors=0
--FILE--
<?php $argv[1]='concorrencia'; require dirname(__DIR__,2).'/testb2.php'; ?>
--EXPECTF--
%Ahas already been bound to another coroutine#%d, reading of the same socket in coroutine#%d at the same time is not allowed%A
