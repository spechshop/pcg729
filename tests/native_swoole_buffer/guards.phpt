--TEST--
Native Buffer borrow rejects clear, constructor, recursive reads and receive before mutation
--EXTENSIONS--
swoole
swoole_buffer_test
--FILE--
<?php
require __DIR__ . '/common.inc';
Swoole\Coroutine\run(function () {
    [$receiver, $sender, $port] = udp_pair();
    $buffer = new Swoole\Buffer(16);
    receive_packet($receiver, $sender, $port, $buffer, "keep\0bytes");
    // Queue data first: an unguarded receive would mutate even without yielding.
    ensure($sender->sendto('127.0.0.1', $port, 'next') === 4);
    $view = swoole_buffer_test_read($buffer, 0, null, function () use (&$buffer, $receiver) {
        rejects(fn() => $buffer->clear());
        rejects(fn() => $buffer->__construct(128));
        rejects(function () use (&$buffer) { swoole_buffer_test_read($buffer); });
        rejects(function () use ($buffer, $receiver) {
            $peer = 'unchanged';
            try { $receiver->recvfromInto($buffer, $peer, 0.001); }
            finally { ensure($peer === 'unchanged'); }
        });
        ensure($buffer->getLength() === 10 && $buffer->getCapacity() === 16);
        ensure($buffer->toString() === "keep\0bytes");
        $other = new Swoole\Buffer(8);
        ensure(swoole_buffer_test_read($other)['length'] === 0);
        $other->clear();
        $fiber = new Fiber(function () { ensure(false); });
        rejects(fn() => $fiber->start(), FiberError::class);
    });
    ensure($view['checksum'] === byte_sum("keep\0bytes"));
    $peer = null;
    ensure($receiver->recvfromInto($buffer, $peer, 0.5) === 4);
    ensure($buffer->toString() === 'next');
    $buffer->clear();
    $receiver->close();
    $sender->close();
    echo "guards and queued receive OK\n";
});
?>
--EXPECT--
guards and queued receive OK
