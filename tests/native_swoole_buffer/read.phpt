--TEST--
Public Buffer C ABI: empty, binary UDP, exact capacity, slices and reuse
--EXTENSIONS--
sockets
swoole
swoole_buffer_test
--FILE--
<?php
require __DIR__ . '/common.inc';
ensure(swoole_buffer_test_contract());
Swoole\Coroutine\run(function () {
    [$receiver, $sender, $port] = udp_pair();
    $buffer = new Swoole\Buffer(2048);
    ensure(swoole_buffer_test_read($buffer) === [
        'length' => 0, 'capacity' => 2048, 'checksum' => 0, 'fiber_blocked' => true,
    ]);
    foreach ([0, 160, 1024, 2048, 160] as $size) {
        $packet = substr(str_repeat("\0\xffabc\0\x80", 300), 0, $size);
        receive_packet($receiver, $sender, $port, $buffer, $packet);
        $view = swoole_buffer_test_read($buffer);
        ensure($view['length'] === $size && $view['checksum'] === byte_sum($packet));
        ensure($buffer->getLength() === $size && $buffer->toString() === $packet);
        ensure(swoole_buffer_test_read($buffer, $size, 0)['length'] === 0);
        ensure(swoole_buffer_test_read($buffer, 0, $size)['length'] === $size);
        if ($size) {
            ensure(swoole_buffer_test_read($buffer, 1)['checksum'] === byte_sum(substr($packet, 1)));
            ensure(swoole_buffer_test_read($buffer, 1, 3)['checksum'] === byte_sum(substr($packet, 1, 3)));
        }
        ensure(swoole_buffer_test_protocol($buffer));
        $buffer->clear();
        ensure($buffer->getLength() === 0 && $buffer->getCapacity() === 2048);
        echo "read $size OK\n";
    }
    receive_packet($receiver, $sender, $port, $buffer, 'old');
    ensure($sender->sendto('127.0.0.1', $port, str_repeat('x', 2049)) === 2049);
    $peer = 'unchanged';
    ensure($receiver->recvfromInto($buffer, $peer, 0.5) === false);
    ensure($receiver->errCode === SOCKET_EMSGSIZE && $peer === 'unchanged');
    ensure(swoole_buffer_test_read($buffer)['length'] === 0);
    receive_packet($receiver, $sender, $port, $buffer, "new\0bytes");
    ensure(swoole_buffer_test_read($buffer)['length'] === 9);
    $receiver->close();
    $sender->close();
    echo "overflow and reuse OK\n";
});
?>
--EXPECT--
read 0 OK
read 160 OK
read 1024 OK
read 2048 OK
read 160 OK
overflow and reuse OK
