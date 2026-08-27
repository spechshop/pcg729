<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Uso: php bench_codec.php <gsm|g729> <encode|decode|roundtrip> [iterations]\n");
    exit(1);
}

$codec = $argv[1];
$operation = $argv[2];
$iterations = isset($argv[3]) ? (int) $argv[3] : 1_000_000;

if ($iterations <= 0) {
    fwrite(STDERR, "iterations deve ser > 0\n");
    exit(1);
}

/*
 * Gera exatamente 20 ms:
 *
 * 8000 Hz * 0.020 = 160 samples
 * 160 * int16 = 320 bytes PCM
 *
 * Gerado uma única vez, fora da região medida.
 */
$pcm = '';

for ($i = 0; $i < 160; $i++) {
    $sample = (int) (12000 * sin(2 * M_PI * 440 * ($i / 8000)));

    // PCM16 little-endian signed
    $pcm .= pack('v', $sample & 0xffff);
}

if (strlen($pcm) !== 320) {
    throw new RuntimeException('PCM de benchmark inválido');
}

switch ($codec) {
    case 'gsm':
        $channel = new gsmChannel();

        $encoded = $channel->encode($pcm);

        if ($encoded === false || strlen($encoded) !== 33) {
            throw new RuntimeException(
                'Falha preparando GSM: ' .
                var_export($encoded === false ? false : strlen($encoded), true)
            );
        }

        break;

    case 'g729':
        $channel = new bcg729Channel();

        /*
         * 320 bytes = dois frames G.729 de 10 ms.
         */
        $encoded = $channel->encode($pcm);

        if ($encoded === false || strlen($encoded) !== 20) {
            throw new RuntimeException(
                'Falha preparando G.729: ' .
                var_export($encoded === false ? false : strlen($encoded), true)
            );
        }

        break;

    default:
        fwrite(STDERR, "Codec inválido: {$codec}\n");
        exit(1);
}

/*
 * Warmup.
 */
for ($i = 0; $i < 10_000; $i++) {
    switch ($operation) {
        case 'encode':
            $result = $channel->encode($pcm);
            break;

        case 'decode':
            $result = $channel->decode($encoded);
            break;

        case 'roundtrip':
            $tmp = $channel->encode($pcm);
            if ($tmp === false) {
                throw new RuntimeException('encode falhou no warmup');
            }

            $result = $channel->decode($tmp);
            break;

        default:
            fwrite(STDERR, "Operação inválida: {$operation}\n");
            exit(1);
    }

    if ($result === false) {
        throw new RuntimeException('Codec retornou false durante warmup');
    }
}

/*
 * Região que o perf vai medir.
 */
$start = hrtime(true);

for ($i = 0; $i < $iterations; $i++) {
    switch ($operation) {
        case 'encode':
            $result = $channel->encode($pcm);
            break;

        case 'decode':
            $result = $channel->decode($encoded);
            break;

        case 'roundtrip':
            $tmp = $channel->encode($pcm);

            if ($tmp === false) {
                throw new RuntimeException('encode falhou');
            }

            $result = $channel->decode($tmp);
            break;
    }

    if ($result === false) {
        throw new RuntimeException("{$codec} {$operation} retornou false");
    }
}

$elapsedNs = hrtime(true) - $start;
$elapsedSec = $elapsedNs / 1e9;

$audioSeconds = $iterations * 0.020;

printf(
    "codec=%s operation=%s iterations=%d wall=%.6fs audio=%.2fs realtime_factor=%.2fx\n",
    $codec,
    $operation,
    $iterations,
    $elapsedSec,
    $audioSeconds,
    $audioSeconds / $elapsedSec
);

$channel->close();