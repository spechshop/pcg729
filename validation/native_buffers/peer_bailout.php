<?php
require dirname(__DIR__,2).'/tests/native_swoole_buffer/common.inc';
$b = new Swoole\Buffer(16);
$weak = WeakReference::create($b);
register_shutdown_function(function () use (&$b,$weak) {
    try { $b->clear(); echo "peer bailout guard released\n"; }
    catch (Throwable $e) { echo 'GUARD LEAK: ',$e->getMessage(),"\n"; }
    $b=null;
    echo 'destroyed=', $weak->get()===null?'yes':'no',"\n";
});
class FatalPeer { function __destruct() { trigger_error('isolated peer destructor bailout', E_USER_ERROR); } }
Swoole\Coroutine\run(function () use ($b) {
    [$rx,$tx,$port]=udp_pair();
    ensure($tx->sendto('127.0.0.1',$port,'abc')===3);
    $peer=new FatalPeer();
    $rx->recvfromInto($b,$peer,.1);
});
