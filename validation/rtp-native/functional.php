<?php
declare(strict_types=1);
require __DIR__ . '/../../libspech/plugins/autoloader.php';
use libspech\Rtp\rtpc;
function check(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); }
function rejects(Closure $call): void { try { $call(); } catch (ValueError|TypeError $e) { return; } throw new RuntimeException('Expected rejection'); }
Co\run(function () {
    $rx = new Swoole\Coroutine\Socket(AF_INET, SOCK_DGRAM, SOL_UDP);
    $tx = new Swoole\Coroutine\Socket(AF_INET, SOCK_DGRAM, SOL_UDP);
    check($rx->bind('127.0.0.1', 0), 'bind');
    $port = $rx->getsockname()['port'];
    $buffer = new Swoole\Buffer(2048);
    $receive = function (string $bytes) use ($rx, $tx, $port, $buffer): int {
        $tx->sendto('127.0.0.1', $port, $bytes);
        $peer = null;
        $n = $rx->recvfromInto($buffer, $peer, 1);
        check($n === strlen($bytes), 'UDP length');
        return $n;
    };
    $base = pack('CCnNN', 0x80, 0x88, 65535, 0xffffffff, 0x87654321);
    $cases = ['', '0', str_repeat('x', 11), $base, $base . "\0\xff\x80"];
    for ($first = 0; $first < 256; ++$first) {
        $bytes = chr($first) . substr($base, 1) . str_repeat('csrc', $first & 15)
            . (($first & 16) ? pack('nn', 0xbede, 2) . 'ext-data' : '') . "\0\xffaudio";
        $cases[] = $bytes;
        $cases[] = substr($bytes, 0, 12 + 4 * ($first & 15) + 2);
    }
    foreach ($cases as $i => $bytes) {
        $n = $receive($bytes);
        $packet = rtpc::fromNativeBuffer($buffer, $n);
        $traditional = new rtpc($bytes);
        $receive('overwrite');
        check(serialize($packet) === serialize($traditional), "Swoole equivalence $i");
    }
    $padded = pack('CCnNN', 0xa1, 8, 1, 2, 3) . 'csrc' . "binary\0\xff\0\0\3";
    $n = $receive($padded);
    $target = new rtpc(null);
    $header = psampler_rtp_parse($buffer, $n, $target);
    check(strlen($header) === 16 && $target->payloadRaw === "binary\0\xff", 'strict padding and CSRC');
    $bytes = pack('CCnNN', 0x91, 8, 1, 2, 3) . 'csrc' . pack('nn', 0xbede, 2) . 'ext-data' . "\0data";
    $n = $receive($bytes);
    check(strlen(psampler_rtp_parse($buffer, $n, $target)) === 28 && $target->payloadRaw === "\0data", 'strict extension');
    for ($length = 0; $length < 28; ++$length) rejects(fn() => psampler_rtp_parse($buffer, $length, new rtpc(null)));
    rejects(fn() => psampler_rtp_parse($buffer, $n + 1, $target));
    rejects(fn() => psampler_rtp_parse($buffer, -1, $target));
    rejects(fn() => psampler_rtp_parse(new stdClass, 0, $target));
    foreach ([pack('CCnNN', 0x40, 8, 1, 2, 3), pack('CCnNN', 0xa0, 8, 1, 2, 3), pack('CCnNN', 0xa0, 8, 1, 2, 3) . "\0", pack('CCnNN', 0xa0, 8, 1, 2, 3) . "\xff"] as $invalid) {
        $n = $receive($invalid);
        rejects(fn() => psampler_rtp_parse($buffer, $n, $target));
        $buffer->clear(); // failed acquisitions must release the borrow
    }
    $rx->close(); $tx->close();
    echo count($cases), " Swoole equivalence cases; strict bounds, extension, padding and reuse OK\n";
});
