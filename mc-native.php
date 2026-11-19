<?php

declare(strict_types=1);

/** Receptor experimental: buildroot/bin/php mc-native.php
 * Exige recvfromInto e psampler_rtp_parse, sem fallback.
 */

use libspech\Rtp\MediaChannel;
use libspech\Rtp\rtpc;
use libspech\Rtp\rtpChannel;
use Swoole\Coroutine;

require __DIR__ . '/libspech/plugins/autoloader.php';

const RTP_BIND_HOST = '127.0.0.1';
const RTP_LISTEN_PORT = 40000;
const RTP_SENDER_HOST = '127.0.0.1';
const RTP_SENDER_PORT = 50000;

if (!function_exists('psampler_rtp_parse')) {
    throw new RuntimeException('Recompile o PHP local: psampler_rtp_parse indisponivel');
}
\Co\run(static function (): void {
    $socket = new \SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    if (!$socket->bind(RTP_BIND_HOST, RTP_LISTEN_PORT)) {
        throw new RuntimeException("Bind falhou em " . RTP_BIND_HOST . ':' . RTP_LISTEN_PORT . ": {$socket->errMsg}");
    }

    $mediaChannel = new MediaChannel($socket, 'lab-pcma-40000');
    $mediaChannel->portList = RTP_LISTEN_PORT;
    $mediaChannel->connectTimeout = 3600; // evita encerrar durante pausas de laboratorio

    // Sem SDP: configurar explicitamente os mesmos PTs nos dois sentidos.
    $codecMapper = [
        8   => 'PCMA/8000/1',
        101 => 'telephone-event/8000/1',
    ];

    $mediaChannel->codecMapper = $codecMapper;
    $mediaChannel->rxCodecMapper = $codecMapper;
    $mediaChannel->txCodecMapper = $codecMapper;
    $mediaChannel->options = [
        'codecMapper' => $codecMapper,
        'config' => [],
        'transcodingEnabled' => false,
    ];
    $mediaChannel->registerPtCodecs($codecMapper);

    // Mantem o processamento normal, incluindo relay, audio e DTMF.
    $mediaChannel->setReceiveOnlyMode(false);
    $mediaChannel->setSettings(['sendSilenceProbeToMembers' => true]);
    $mediaChannel->requireNativeRtpParser();

    $mediaChannel->addMember([
        'address'       => RTP_SENDER_HOST,
        'port'          => RTP_SENDER_PORT,
        'codec'         => 'PCMA',
        'pt'            => 8,
        'txPt'          => 8,
        'rxPt'          => 8,
        'frequency'     => 8000,
        'channels'      => 1,
        'ptime'         => 20,
        'timestamp'     => time(),
        'config'        => [],
        'leg'           => 'a',
        'txCodecMapper' => $codecMapper,
        'rxCodecMapper' => $codecMapper,
    ]);

    $packets = 0;
    $bytes = 0;
    $firstPeer = false;
    $prevSeq = null;
    $seqGaps = 0;

    $mediaChannel->onReceive(static function (
        rtpc $packet,
        array $peer,
        MediaChannel $channel,
        rtpChannel $rtpChannel
    ) use (&$packets, &$bytes, &$firstPeer, &$prevSeq, &$seqGaps): void {

        if (!$firstPeer) {
            printf("Primeiro RTP: %s:%d | PT=%d | SSRC=%u\n",
                (string)$peer['address'], (int)$peer['port'], $packet->payloadType, $packet->ssrc);
            $firstPeer = true;
        }

        ++$packets;
        $bytes += strlen($packet->payloadRaw);
        if ($prevSeq !== null) {
            $delta = ($packet->sequence - $prevSeq) & 0xFFFF;
            if ($delta > 0 && $delta < 0x8000) {
                if ($delta > 1) $seqGaps += ($delta - 1);
                $prevSeq = $packet->sequence;
            }
        } else {
            $prevSeq = $packet->sequence;
        }
    });

    $mediaChannel->onDtmf(static function (int $event): void {
        printf("DTMF: evento=%d\n", $event);
    });
    $mediaChannel->packetOnTimeout(static function (): void {
        echo "Timeout RTP: sem pacotes por 3600 segundos\n";
    });
    $mediaChannel->onStart(static function (): void {
        echo "MediaChannel iniciado. Aguardando RTP...\n";
    });

    printf("Listener PCMA 8 kHz em %s:%d | esperado %s:%d | modo=%s\n",
        RTP_BIND_HOST, RTP_LISTEN_PORT, RTP_SENDER_HOST, RTP_SENDER_PORT,
        'recvfromInto + psampler_rtp_parse (obrigatorio)');

    $mediaChannel->start();

    $lastPackets = 0;
    $lastBytes = 0;
    $lastTime = hrtime(true);
    try {
        while ($mediaChannel->active && !$socket->isClosed()) {
            Coroutine::sleep(1);
            $now = hrtime(true);
            $elapsed = ($now - $lastTime) / 1_000_000_000;
            if ($elapsed <= 0) continue;

            printf("RX: %.1f PPS | %.1f KiB/s | total=%d | lacunas_seq=%d\n",
                ($packets - $lastPackets) / $elapsed,
                ($bytes - $lastBytes) / $elapsed / 1024,
                $packets,
                $seqGaps);
            $lastPackets = $packets;
            $lastBytes = $bytes;
            $lastTime = $now;
        }
    } finally {
        $mediaChannel->close();
        Coroutine::sleep(0.01); // entrega fechamento ao receive loop, que e dono do socket
    }
});
