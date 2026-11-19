<?php

declare(strict_types=1);

/**
 * Emissor RTP de laboratorio (libspech): PCMA + DTMF RFC 4733.
 * Porta local 50000, destino 127.0.0.1:40000.
 * Audio em quadros de 20 ms e DTMF aleatorio 0-9 a cada 3000 ms.
 * Pressione Ctrl+C para encerrar.
 */

use libspech\Rtp\rtpChannel;
use Swoole\Coroutine;
use Swoole\Timer;

require __DIR__ . '/libspech/plugins/autoloader.php';

const RTP_SOURCE_HOST = '127.0.0.1';
const RTP_SOURCE_PORT = 50000;
const RTP_DEST_HOST = '127.0.0.1';
const RTP_DEST_PORT = 40000;
const RTP_PTIME_MS = 20;
const RTP_SAMPLE_RATE = 8000;
const RTP_SAMPLES = 160;
const RTP_INTERVAL_NS = RTP_PTIME_MS * 1_000_000;
const RTP_DTMF_PT = 101;
const RTP_DTMF_DURATION_MS = 160;
const RTP_DTMF_PROGRESS_FRAMES = 8; // 160 ms / 20 ms
const RTP_DTMF_END_RETRANSMITS = 3;
const RTP_DTMF_TOTAL_FRAMES = RTP_DTMF_PROGRESS_FRAMES + RTP_DTMF_END_RETRANSMITS;

\Co\run(static function (): void {
    $socket = new \SocketMutable(AF_INET, SOCK_DGRAM, SOL_UDP);
    if (!$socket->bind(RTP_SOURCE_HOST, RTP_SOURCE_PORT)) {
        throw new RuntimeException('Bind falhou em ' . RTP_SOURCE_HOST . ':' . RTP_SOURCE_PORT . ': ' . $socket->errMsg);
    }

    // O rtpChannel controla a sequencia, o timestamp e o SSRC.
    // Para DTMF ele usa o mesmo SSRC, PT 101 e timestamp fixo do evento.
    $rtp = new rtpChannel(8, RTP_SAMPLE_RATE, RTP_PTIME_MS);
    $rtp->setNewPtDTMF(RTP_DTMF_PT);

    // PCMA 8 kHz/mono: 160 bytes de silencio por quadro de 20 ms.
    $silence = str_repeat("\xD5", RTP_SAMPLES);
    $nextSend = hrtime(true);
    $lastPrint = $nextSend;
    $audioSent = 0;
    $dtmfSent = 0;
    $errors = 0;
    $skippedSlots = 0;
    $lastTotal = 0;

    $pendingDigit = null;
    $dtmf = null;

    // Timer apenas agenda o proximo evento; o envio continua em uma unica
    // coroutine, para nao disputar sequence/timestamp no mesmo rtpChannel.
    $timerId = Timer::tick(3000, static function () use (&$pendingDigit): void {
        if ($pendingDigit === null) {
            $pendingDigit = random_int(0, 9);
        }
    });

    printf(
        "RTP PCMA %s:%d -> %s:%d | ptime=20ms | DTMF aleatorio a cada 3000ms | Ctrl+C\n",
        RTP_SOURCE_HOST, RTP_SOURCE_PORT, RTP_DEST_HOST, RTP_DEST_PORT
    );

    try {
        while (true) {
            $now = hrtime(true);
            if ($now < $nextSend) {
                @Coroutine::sleep(($nextSend - $now) / 1_000_000_000);
                continue;
            }

            $behind = $now - $nextSend;
            if ($behind >= RTP_INTERVAL_NS) {
                if ($dtmf === null) {
                    // Nao compensar atraso de audio com rajadas.
                    $missed = intdiv($behind, RTP_INTERVAL_NS);
                    $skippedSlots += $missed;
                    $rtp->advanceTimestampBySamples($missed * RTP_SAMPLES);
                    $nextSend += $missed * RTP_INTERVAL_NS;
                } else {
                    // Durante DTMF, preservar progressao e retransmissoes.
                    $nextSend = $now;
                }
            }

            if ($dtmf === null && $pendingDigit !== null) {
                $dtmf = [
                    'digit' => $pendingDigit,
                    'timestamp' => $rtp->timestamp,
                    'step' => 0,
                ];
                printf("DTMF iniciado: %d (PT=%d)\n", $dtmf['digit'], RTP_DTMF_PT);
                $pendingDigit = null;
            }

            if ($dtmf !== null) {
                $step = $dtmf['step'];
                $isEnd = $step >= RTP_DTMF_PROGRESS_FRAMES;
                $duration = $isEnd
                    ? intdiv(RTP_DTMF_DURATION_MS * RTP_SAMPLE_RATE, 1000)
                    : ($step + 1) * RTP_SAMPLES;
                $flags = ($isEnd ? 0x80 : 0) | 10; // E-bit e volume 10
                $eventPayload = pack('CCn', $dtmf['digit'], $flags, (int)$duration);

                // Marker somente no primeiro pacote; o timestamp fica fixo.
                $packet = $rtp->buildDtmfForwardPacket(
                    $eventPayload,
                    $dtmf['timestamp'],
                    $step === 0
                );
                ++$dtmfSent;
                ++$dtmf['step'];
                if ($dtmf['step'] >= RTP_DTMF_TOTAL_FRAMES) {
                    // O evento ocupa 11 slots de 20 ms: 8 de progresso e
                    // 3 retransmissoes E. Avanca a timeline pelo tempo efetivo
                    // antes de voltar a enviar audio, sem rajadas.
                    $rtp->advanceTimestampBySamples(RTP_DTMF_TOTAL_FRAMES * RTP_SAMPLES);
                    $dtmf = null;
                }
            } else {
                $packet = $rtp->buildAudioPacket($silence);
                ++$audioSent;
            }

            $written = $socket->sendto(RTP_DEST_HOST, RTP_DEST_PORT, $packet);
            if ($written === false || $written !== strlen($packet)) {
                ++$errors;
            }

            $nextSend += RTP_INTERVAL_NS;
            $now = hrtime(true);
            if ($now - $lastPrint >= 1_000_000_000) {
                $elapsed = ($now - $lastPrint) / 1_000_000_000;
                $total = $audioSent + $dtmfSent;
                printf(
                    "TX: %.1f PPS | audio=%d | DTMF=%d | erros=%d | slots_perdidos=%d\n",
                    ($total - $lastTotal) / $elapsed,
                    $audioSent,
                    $dtmfSent,
                    $errors,
                    $skippedSlots
                );
                $lastTotal = $total;
                $lastPrint = $now;
            }
        }
    } finally {
        Timer::clear($timerId);
        $socket->close();
    }
});
