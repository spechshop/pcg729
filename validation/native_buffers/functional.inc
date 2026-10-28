<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/tests/native_swoole_buffer/common.inc';
Swoole\Coroutine\run(function () {
    [$rx, $tx, $port] = udp_pair();
    ensure($tx->bind('127.0.0.1', 0));
    $txPort = $tx->getsockname()['port'];
    $b = new Swoole\Buffer(2048);
    foreach ([0, 1, 12, 160, 1024, 2048] as $n) {
        $s = substr(str_repeat("\0\xff\x80ab", 410), 0, $n);
        ensure($tx->sendto('127.0.0.1', $port, $s) === $n);
        $peer = 'sentinel';
        ensure($rx->recvfromInto($b, $peer, .2) === $n);
        ensure($b->toString() === $s && $b->getLength() === $n);
        ensure($peer === ['address'=>'127.0.0.1','port'=>$txPort]);
    }
    foreach ([2049, 4096, 65507] as $n) {
        ensure($tx->sendto('127.0.0.1', $port, str_repeat('x', $n)) === $n);
        $peer = 'sentinel';
        ensure($rx->recvfromInto($b, $peer, .2) === false);
        ensure($rx->errCode === SOCKET_EMSGSIZE && $peer === 'sentinel');
        ensure($b->getLength() === 0 && $b->getCapacity() === 2048);
        receive_packet($rx,$tx,$port,$b,"recover\0");
    }
    for ($i=0; $i<10000; ++$i) {
        $s = pack('V', $i) . "\0\xff" . str_repeat('q',166);
        receive_packet($rx,$tx,$port,$b,$s);
        ensure($b->toString() === $s);
    }
    $peer = 'timeout'; $ticks = 0;
    $cid = Swoole\Coroutine::create(function () use ($rx,$b,&$peer) {
        ensure($rx->recvfromInto($b,$peer,.03) === false);
        ensure($rx->errCode === SOCKET_ETIMEDOUT);
    });
    while (Swoole\Coroutine::exists($cid)) { Swoole\Coroutine::sleep(.001); ++$ticks; }
    ensure($ticks > 1 && $peer === 'timeout' && $b->getLength() === 0);
    $done = new Swoole\Coroutine\Channel(1);
    $cid = Swoole\Coroutine::create(function () use ($rx,$b,$done) {
        $peer = null;
        ensure($rx->recvfromInto($b,$peer,5) === false);
        $done->push($rx->errCode);
    });
    $rx2 = new Swoole\Coroutine\Socket(AF_INET,SOCK_DGRAM,0);
    ensure($rx2->bind('127.0.0.1',0));
    rejects(function () use ($rx2,$b) { $p='unchanged'; try {$rx2->recvfromInto($b,$p,.01);} finally {ensure($p==='unchanged');} });
    $b2 = new Swoole\Buffer(2048);
    ensure($rx->cancel(SWOOLE_EVENT_READ));
    $cancelCode = $done->pop(.5);
    ensure($cancelCode === SOCKET_ECANCELED);
    receive_packet($rx,$tx,$port,$b,"after\0cancel");
    receive_packet($rx,$tx,$port,$b2,'independent');
    $rx2->close(); $rx->close(); $tx->close();
    echo json_encode(['sizes'=>[0,1,12,160,1024,2048], 'oversized'=>[2049,4096,65507], 'reuse'=>10000, 'scheduler_ticks'=>$ticks, 'cancel_code'=>$cancelCode,'status'=>'PASS']),"\n";
});
