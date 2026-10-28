--TEST--
Optional psampler adapter uses the exact public v1 producer contract
--EXTENSIONS--
psampler
swoole
swoole_buffer_test
--SKIPIF--
<?php
if (!ByteBuffer::supportsNativeBuffer(new Swoole\Buffer(16))) {
    die('skip rebuild fixture with --enable-swoole-buffer-test-psampler; do not load psampler_swoole');
}
?>
--FILE--
<?php
require __DIR__ . '/common.inc';
Swoole\Coroutine\run(function () {
    [$receiver, $sender, $port] = udp_pair();
    $buffer = new Swoole\Buffer(2048);
    $destination = new ByteBuffer(4);
    $destination->appendBuffer($buffer);
    ensure($destination->length() === 0);
    foreach ([160, 1024, 2048] as $size) {
        $packet = substr(str_repeat("\0\xffa\x80", 512), 0, $size);
        receive_packet($receiver, $sender, $port, $buffer, $packet);
        $view = swoole_buffer_test_read($buffer, 0, null, function () use ($buffer, $receiver) {
            rejects(fn() => $buffer->clear());
            rejects(function () use ($buffer, $receiver) {
                $peer = null;
                $receiver->recvfromInto($buffer, $peer, 0.001);
            });
        }, 3);
        ensure($view['length'] === $size && $view['checksum'] === byte_sum($packet));
        $destination->appendBuffer($buffer, 1, $size - 2);
        $buffer->clear();
        ensure($destination->pop($size - 2) === substr($packet, 1, $size - 2));
    }
    $receiver->close();
    $sender->close();
});
$source = new Swoole\Buffer(16);
$weak = WeakReference::create($source);
rejects(function () use (&$source) {
    swoole_buffer_test_read($source, 0, null, function () {
        throw new RuntimeException('psampler reader');
    }, 3);
}, RuntimeException::class);
$source->clear();
swoole_buffer_test_read($source, 0, null, function () use (&$source, $weak) {
    $source = null;
    ensure($weak->get() !== null);
}, 3);
ensure($weak->get() === null);
echo "psampler producer ABI OK\n";
?>
--EXPECT--
psampler producer ABI OK
