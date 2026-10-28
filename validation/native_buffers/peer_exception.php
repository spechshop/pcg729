<?php
require dirname(__DIR__,2).'/tests/native_swoole_buffer/common.inc';
class ThrowingPeer { function __destruct() { throw new RuntimeException('recoverable peer destructor'); } }
Swoole\Coroutine\run(function () {
    [$rx,$tx,$port]=udp_pair(); $b=new Swoole\Buffer(16);
    ensure($tx->sendto('127.0.0.1',$port,'abc')===3);
    $peer=new ThrowingPeer();
    rejects(function () use ($rx,$b,&$peer) {$rx->recvfromInto($b,$peer,.1);}, RuntimeException::class);
    $b->clear(); ensure(swoole_buffer_test_read($b)['length']===0);
    receive_packet($rx,$tx,$port,$b,'after');
    $rx->close(); $tx->close(); echo "peer exception cleanup PASS\n";
});
