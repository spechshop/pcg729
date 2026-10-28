--TEST--
Native reads reject pending UDP receive and allow timeout/cancellation cleanup
--EXTENSIONS--
swoole
swoole_buffer_test
--FILE--
<?php
require __DIR__ . '/common.inc';
Swoole\Coroutine\run(function () {
    [$receiver, $sender, $port] = udp_pair();
    $buffer = new Swoole\Buffer(16);
    $done = new Swoole\Coroutine\Channel(1);
    $cid = Swoole\Coroutine::create(function () use ($buffer, $receiver, $done) {
        $peer = null;
        ensure($receiver->recvfromInto($buffer, $peer, 5.0) === false);
        $done->push(true);
    });
    rejects(function () use (&$buffer) { swoole_buffer_test_read($buffer); });
    rejects(fn() => $buffer->clear());
    ensure(Swoole\Coroutine::cancel($cid));
    ensure($done->pop(0.5));
    ensure(swoole_buffer_test_read($buffer)['length'] === 0);
    $peer = null;
    ensure($receiver->recvfromInto($buffer, $peer, 0.001) === false);
    ensure(swoole_buffer_test_read($buffer)['length'] === 0);
    receive_packet($receiver, $sender, $port, $buffer, "after\0cancel");
    ensure(swoole_buffer_test_read($buffer)['length'] === 12);
    $receiver->close();
    $sender->close();
    echo "pending receive cleanup OK\n";
});
?>
--EXPECT--
pending receive cleanup OK
