--TEST--
Buffer native reader validates objects, initialization, ranges and error cleanup
--EXTENSIONS--
swoole
swoole_buffer_test
--FILE--
<?php
require __DIR__ . '/common.inc';
$invalid = new stdClass();
rejects(function () use (&$invalid) { swoole_buffer_test_read($invalid); });
$uninitialized = swoole_buffer_test_uninitialized();
rejects(function () use (&$uninitialized) { swoole_buffer_test_read($uninitialized); });
$uninitialized->__construct(16);
ensure(swoole_buffer_test_read($uninitialized)['length'] === 0);
Swoole\Coroutine\run(function () {
    [$receiver, $sender, $port] = udp_pair();
    $buffer = new Swoole\Buffer(16);
    receive_packet($receiver, $sender, $port, $buffer, "a\0bc");
    foreach ([[-1, null], [0, -1], [5, null], [4, 1], [3, 2], [PHP_INT_MAX, 1], [1, PHP_INT_MAX]] as [$offset, $length]) {
        rejects(function () use (&$buffer, $offset, $length) {
            swoole_buffer_test_read($buffer, $offset, $length);
        }, ValueError::class);
        ensure($buffer->toString() === "a\0bc");
        ensure(swoole_buffer_test_read($buffer)['length'] === 4);
    }
    rejects(function () use (&$buffer) { swoole_buffer_test_read($buffer, 0, null, null, 1); });
    rejects(function () use (&$buffer) {
        swoole_buffer_test_read($buffer, 0, null, function () { throw new RuntimeException('reader'); });
    }, RuntimeException::class);
    $buffer->clear();
    receive_packet($receiver, $sender, $port, $buffer, 'reused');
    ensure(swoole_buffer_test_read($buffer)['length'] === 6);
    $receiver->close();
    $sender->close();
});
echo "validation and release OK\n";
?>
--EXPECT--
validation and release OK
