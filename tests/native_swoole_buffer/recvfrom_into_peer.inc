<?php
require __DIR__ . '/common.inc';
// $typed and $fatal are chosen by the isolated PHPT process.
$buffer = new Swoole\Buffer(16);
[$receiver, $sender, $port] = udp_pair();
$bufferWeak = WeakReference::create($buffer);
$socketWeak = WeakReference::create($receiver);
$bufferRefs = swoole_buffer_test_refcount($buffer);
$socketRefs = swoole_buffer_test_refcount($receiver);
$timeout = $receiver->getOption(SOL_SOCKET, SO_RCVTIMEO);
$destructors = 0;
$coroutine = getenv('RECV_COROUTINE') === '1';
class RecvIntoPeer {
    function __destruct() {
        global $destructors, $buffer, $receiver, $bufferRefs, $socketRefs, $fatal;
        ++$destructors;
        ensure(swoole_buffer_test_refcount($buffer) === $bufferRefs + 2); // arg + receive pin
        ensure(swoole_buffer_test_refcount($receiver) >= $socketRefs + 1);
        rejects(fn() => $buffer->clear());
        rejects(function () use (&$buffer) { swoole_buffer_test_read($buffer); });
        if ($fatal) {
            trigger_error('recvfromInto peer bailout', E_USER_ERROR);
        }
        throw new RuntimeException('recvfromInto peer exception');
    }
}
class RecvIntoHolder { public array|RecvIntoPeer $peer; }
$holder = new RecvIntoHolder();
if ($typed) {
    $holder->peer = new RecvIntoPeer();
    $peer =& $holder->peer;
} else {
    $peer = new RecvIntoPeer();
}
register_shutdown_function(function () use ($bufferWeak, $socketWeak, $bufferRefs, $socketRefs, $timeout, $coroutine) {
    global $buffer, $receiver, $sender, $destructors, $fatal;
    ensure($destructors === 1);
    $buffer->clear();
    ensure(swoole_buffer_test_read($buffer)['length'] === 0);
    // A fatal keeps the argument/frame and diagnostic trace alive until executor
    // teardown (also reproduced by an ordinary PHP method, without native pins).
    $frameBufferRefs = $fatal ? 2 : 0;
    $frameSocketRefs = $fatal ? 1 : 0;
    ensure(swoole_buffer_test_refcount($buffer) === $bufferRefs + $frameBufferRefs);
    ensure(swoole_buffer_test_refcount($receiver) === $socketRefs + $frameSocketRefs);
    ensure($receiver->getOption(SOL_SOCKET, SO_RCVTIMEO) === $timeout);
    $receiver->close(); $sender->close();
    $buffer = null; $receiver = null;
    if (!$fatal) {
        ensure($bufferWeak->get() === null && $socketWeak->get() === null);
        echo "recvfromInto guard, pins, timeout and destruction OK\n";
    } else {
        echo "recvfromInto guard, pins and timeout OK; fatal frame refs match PHP control\n";
    }
});
$operation = function () {
    global $buffer, $receiver, $sender, $port, $peer, $fatal;
    ensure($sender->sendto('127.0.0.1', $port, 'abc') === 3);
    try {
        $receiver->recvfromInto($buffer, $peer, .125);
        throw new LogicException('unexpected normal return');
    } catch (RuntimeException $e) {
        ensure(!$fatal && $e->getMessage() === 'recvfromInto peer exception');
        ensure($buffer->getLength() === 0);
        $buffer->clear();
        receive_packet($receiver, $sender, $port, $buffer, 'reused');
        ensure($buffer->toString() === 'reused');
        echo "recvfromInto exception recovery OK\n";
    }
};
if ($coroutine) {
    Swoole\Coroutine\run($operation);
} else {
    $operation();
}
ensure(!$fatal);
