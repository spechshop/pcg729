--TEST--
recvfromInto balances pins on UDP, timeout, cancellation, error, overflow and peer aliasing
--EXTENSIONS--
sockets
swoole
swoole_buffer_test
--FILE--
<?php
require __DIR__ . '/common.inc';
Swoole\Coroutine\run(function () {
    [$rx, $tx, $port] = udp_pair();
    $buffer = new Swoole\Buffer(2048);
    $bRefs = swoole_buffer_test_refcount($buffer);
    $sRefs = swoole_buffer_test_refcount($rx);
    $check = function () use (&$buffer, &$rx, $bRefs, $sRefs) {
        $buffer->clear();
        ensure(swoole_buffer_test_protocol($buffer));
        ensure(swoole_buffer_test_refcount($buffer) === $bRefs);
        ensure(swoole_buffer_test_refcount($rx) === $sRefs);
    };
    foreach ([0, 160, 172, 1024, 2048, 2049, 4096, 65507] as $size) {
        $packet = substr(str_repeat("\0\xff\x80ab", 13102), 0, $size);
        ensure($tx->sendto('127.0.0.1', $port, $packet) === $size);
        $peer = 'unchanged';
        $result = $rx->recvfromInto($buffer, $peer, .5);
        if ($size > 2048) {
            ensure($result === false && $rx->errCode === SOCKET_EMSGSIZE);
            ensure($peer === 'unchanged' && $buffer->getLength() === 0);
        } else {
            ensure($result === $size && $buffer->toString() === $packet);
            ensure($peer['address'] === '127.0.0.1' && $peer['port'] > 0);
        }
        $check();
    }
    $peer = 'timeout';
    ensure($rx->recvfromInto($buffer, $peer, .001) === false);
    ensure($rx->errCode === SOCKET_ETIMEDOUT && $peer === 'timeout');
    $check();
    receive_packet($rx, $tx, $port, $buffer, 'after timeout');
    $check();
    $done = new Swoole\Coroutine\Channel(1);
    $cid = Swoole\Coroutine::create(function () use (&$rx, &$buffer, $done) {
        $peer = 'cancel';
        ensure($rx->recvfromInto($buffer, $peer, 5) === false);
        ensure($rx->errCode === SOCKET_ECANCELED && $peer === 'cancel');
        $done->push(true);
    });
    ensure($rx->cancel(SWOOLE_EVENT_READ));
    ensure($done->pop(.5));
    $check();
    receive_packet($rx, $tx, $port, $buffer, 'after cancel');
    $check();
    class IntPeer { public int $peer = 42; }
    $holder = new IntPeer();
    ensure($tx->sendto('127.0.0.1', $port, 'abc') === 3);
    rejects(function () use ($rx, $buffer, $holder) {
        $rx->recvfromInto($buffer, $holder->peer, .5);
    }, TypeError::class);
    ensure($holder->peer === 42 && $buffer->getLength() === 0);
    $check();
    $tcp = new Swoole\Coroutine\Socket(AF_INET, SOCK_STREAM, 0);
    ensure($tcp->recvfromInto($buffer, $peer, .1) === false);
    ensure($tcp->errCode === SOCKET_EPROTONOSUPPORT);
    $check();
    $tcp->close();
    // The output argument can replace the last visible owner of the buffer.
    $aliased = new Swoole\Buffer(16);
    $weak = WeakReference::create($aliased);
    ensure($tx->sendto('127.0.0.1', $port, 'alias') === 5);
    ensure($rx->recvfromInto($aliased, $aliased, .5) === 5);
    ensure(is_array($aliased) && $weak->get() === null);
    $rx->close();
    ensure($rx->recvfromInto($buffer, $peer, .1) === false);
    $check();
    $tx->close();
    // A suspended receive retains valid socket and buffer objects after the
    // PHP variables are cleared, and releases them when the receive completes.
    [$pendingRx, $pendingTx, $pendingPort] = udp_pair();
    $pendingBuffer = new Swoole\Buffer(16);
    $bWeak = WeakReference::create($pendingBuffer);
    $rWeak = WeakReference::create($pendingRx);
    $cid = Swoole\Coroutine::create(function () use (&$pendingRx, &$pendingBuffer) {
        $peer = null;
        ensure($pendingRx->recvfromInto($pendingBuffer, $peer, .5) === 4);
    });
    $pendingRx = null; $pendingBuffer = null;
    ensure($bWeak->get() !== null && $rWeak->get() !== null);
    ensure($pendingTx->sendto('127.0.0.1', $pendingPort, 'done') === 4);
    while (Swoole\Coroutine::exists($cid)) { Swoole\Coroutine::sleep(.001); }
    ensure($bWeak->get() === null && $rWeak->get() === null);
    $pendingTx->close();
    echo "UDP, errors, pins, aliasing and suspended lifetime OK\n";
});
--EXPECT--
UDP, errors, pins, aliasing and suspended lifetime OK
