<?php
declare(strict_types=1);
$slice=getenv('RTP_STUDY_MODE')==='slice';
require __DIR__.'/autoloader.php';
use libspech\Rtp\MediaChannel;
use libspech\Rtp\rtpc;
function check(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); }
Co\run(function () use ($slice) {
    $rx = new SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    $sender = new Swoole\Coroutine\Socket(AF_INET, SOCK_DGRAM, SOL_UDP);
    $sink = new Swoole\Coroutine\Socket(AF_INET, SOCK_DGRAM, SOL_UDP);
    foreach ([$rx, $sender, $sink] as $s) check($s->bind('127.0.0.1', 0), 'bind');
    $port = $rx->getsockname()['port'];
    $channel = new MediaChannel($rx, 'native-functional');
    $map = [8 => 'PCMA/8000/1', 0 => 'PCMU/8000/1', 101 => 'telephone-event/8000/1'];
    $channel->codecMapper = $channel->rxCodecMapper = $channel->txCodecMapper = $map;
    $channel->options = ['codecMapper' => $map, 'config' => [], 'transcodingEnabled' => true];
    $channel->registerPtCodecs($map);
    $channel->setReceiveOnlyMode(false);
    $channel->requireNativeRtpParser();
    $channel->setSettings(['sendSilenceProbeToMembers' => true]);
    foreach ([[$sender, 'PCMA', 8], [$sink, 'PCMU', 0]] as [$socket, $codec, $pt]) {
        $channel->addMember(['address' => '127.0.0.1', 'port' => $socket->getsockname()['port'], 'codec' => $codec,
            'pt' => $pt, 'txPt' => $pt, 'rxPt' => $pt, 'frequency' => 8000, 'channels' => 1, 'ptime' => 20,
            'timestamp' => time(), 'config' => [], 'txCodecMapper' => $map, 'rxCodecMapper' => $map]);
    }
    $packets = []; $events = [];
    $channel->onReceive(function (rtpc $packet, array $peer) use (&$packets, $sender, $slice) {
        check($peer['port'] === $sender->getsockname()['port'], 'peer association');
        check((new ReflectionProperty(rtpc::class, 'rawPacket'))->isInitialized($packet) === $slice, 'selected parser verified by property state');
        $packets[] = $packet;
    });
    $channel->onDtmf(function (int $event) use (&$events) { $events[] = $event; });
    $channel->start();
    try {
        $payload = str_repeat("\xd5", 160);
        for ($i = 0; $i < 3; ++$i) {
            $sender->sendto('127.0.0.1', $port, pack('CCnNN', 0x80, 8, $i, 160 * $i, 123) . $payload);
            Swoole\Coroutine::sleep(0.02);
        }
        $audio = false;
        for ($i = 0; $i < 10; ++$i) {
            $peer = null; $data = $sink->recvfrom($peer, 0.2);
            if ($data === false) break;
            $rtp = new rtpc($data);
            if ($rtp->payloadType === 0 && $rtp->payloadRaw === encodePcmToPcmu(decodePcmaToPcm($payload))) $audio = true;
        }
        foreach ([0x0a, 0x8a, 0x8a, 0x8a] as $i => $flags) {
            $sender->sendto('127.0.0.1', $port, pack('CCnNNCCn', 0x80, 101, 3 + $i, 1000, 123, 5, $flags, 160));
            Swoole\Coroutine::sleep(0.02);
        }
        check(count($packets) === 3, 'audio callbacks');
        check($audio, 'PCMA decoded and transcoded to PCMU over real MediaChannel');
        check(in_array(5, $events, true), 'DTMF event');
        check($packets[0]->sequence === 0 && $packets[0]->payloadRaw === $payload, 'retained snapshot');
        $forwarded = false;
        for ($i = 0; $i < 10; ++$i) {
            $peer = null; $data = $sink->recvfrom($peer, 0.1);
            if ($data === false) break;
            $p = new rtpc($data);
            if ($p->payloadType === 101 && ord($p->payloadRaw[0]) === 5) $forwarded = true;
        }
        check($forwarded, 'DTMF forwarding');
        echo "MediaChannel: native UDP, peers, audio transcode, callbacks, DTMF and forwarding OK\n";
    } finally {
        $channel->close(); Swoole\Coroutine::sleep(0.25); $sender->close(); $sink->close();
    }
});
