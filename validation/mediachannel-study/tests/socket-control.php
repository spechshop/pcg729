<?php
require __DIR__.'/../lab/baseline/plugins/Utils/network/SocketMutable.php';
function fds(){return count(glob('/proc/self/fd/*'));}
$out=[];
Swoole\Coroutine\run(function()use(&$out){foreach(['Swoole\\Coroutine\\Socket','SocketMutable'] as $cls){$b=fds();$s=new $cls(AF_INET,SOCK_DGRAM,SOL_UDP);$s->bind('127.0.0.1',0);$a=fds();$ret=$s->close();$closed=$s->isClosed();unset($s);gc_collect_cycles();Swoole\Coroutine::sleep(.02);$out[]=compact('cls','b','a','ret','closed')+['after'=>fds()];}});$out[]=['after_reactor'=>fds()];echo json_encode($out)."\n";
