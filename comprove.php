<?php
declare(strict_types=1);

const EVENTS = [
    'ring.started',
    'ring.ended',
    'ring.cadence_detected',
    'ring.interrupted',
    'speech.started',
    'speech.ended',
    'signal.changed',
];

$failures = 0;

function verify(bool $ok, string $description): void
{
    global $failures;

    echo ($ok ? '[OK]     ' : '[FALHOU] ') . $description . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
}

function countEvents(array $events, string $type): int
{
    return count(array_filter(
        $events,
        static fn(array $e): bool => $e['type'] === $type
    ));
}

function eventsBetween(
    array $events,
    string $type,
    int $start,
    int $end
): array {
    return array_values(array_filter(
        $events,
        static fn(array $e): bool =>
            $e['type'] === $type &&
            $e['at_ms'] >= $start &&
            $e['at_ms'] <= $end
    ));
}

function analyze(string $pcm): array
{
    $analyzer = new AcousticStreamAnalyzer(8000, 20);
    $events = [];

    foreach (EVENTS as $type) {
        $analyzer->on(
            $type,
            static function (array $event) use (&$events): void {
                $events[] = $event;
            }
        );
    }

    $start = hrtime(true);
    $length = strlen($pcm);

    // Simula frames PCM de 20 ms.
    for ($offset = 0; $offset < $length; $offset += 320) {
        $analyzer->push(substr($pcm, $offset, 320));
    }

    $elapsed = (hrtime(true) - $start) / 1_000_000;

    return [
        'events' => $events,
        'state' => $analyzer->state(),
        'elapsed_ms' => $elapsed,
    ];
}

function readWav(string $path): string
{
    $wav = file_get_contents($path);

    if ($wav === false ||
        substr($wav, 0, 4) !== 'RIFF' ||
        substr($wav, 8, 4) !== 'WAVE') {
        throw new RuntimeException('WAV inválido: ' . $path);
    }

    $offset = 12;
    $length = strlen($wav);
    $format = null;
    $pcm = null;

    while ($offset + 8 <= $length) {
        $id = substr($wav, $offset, 4);
        $size = unpack('V', substr($wav, $offset + 4, 4))[1];

        $offset += 8;

        if ($size > $length - $offset) {
            throw new RuntimeException('Chunk WAV incompleto');
        }

        if ($id === 'fmt ') {
            if ($size < 16) {
                throw new RuntimeException('Formato WAV inválido');
            }

            $format = unpack(
                'vcodec/vchannels/Vrate/VbyteRate/vblockAlign/vbits',
                substr($wav, $offset, 16)
            );
        }

        if ($id === 'data') {
            $pcm = substr($wav, $offset, $size);
        }

        $offset += $size + ($size % 2);
    }

    if ($format === null ||
        $format['codec'] !== 1 ||
        $format['channels'] !== 1 ||
        $format['rate'] !== 8000 ||
        $format['bits'] !== 16 ||
        $pcm === null) {
        throw new RuntimeException(
            'É necessário WAV PCM16LE mono 8000 Hz'
        );
    }

    return $pcm;
}

function tone(float $frequency, int $durationMs): string
{
    $pcm = '';
    $samples = $durationMs * 8;

    for ($i = 0; $i < $samples; $i++) {
        $value = (int) round(
            12000 * sin(2 * M_PI * $frequency * $i / 8000)
        );

        $pcm .= pack('v', $value & 0xffff);
    }

    return $pcm;
}

if (!class_exists('AcousticStreamAnalyzer')) {
    exit("ERRO: AcousticStreamAnalyzer não encontrada.\n");
}

echo 'PHP: ' . PHP_VERSION . PHP_EOL;
echo 'psampler: ' . phpversion('psampler') . PHP_EOL;

verify(
    phpversion('psampler') === '0.7.0',
    'Versão psampler 0.7.0'
);

echo "\n========== WAV REAL ==========\n";

$path = $argv[1] ??
    '/home/lotus/projetos/libspech/rec_postal.wav';

$pcm = readWav($path);
$result = analyze($pcm);
$events = $result['events'];

printf(
    "Áudio: %.3f segundos\n",
    strlen($pcm) / 16000
);

printf(
    "Processamento: %.3f ms\n\n",
    $result['elapsed_ms']
);

echo "LINHA DO TEMPO\n";

foreach ($events as $event) {
    printf(
        "%8d ms | %-24s | %-17s | %s\n",
        $event['at_ms'],
        $event['type'],
        $event['signal'] ?? '-',
        $event['reason'] ?? '-'
    );
}

echo "\nVALIDAÇÃO DO WAV\n";

verify(
    countEvents($events, 'ring.started') === 6,
    'Seis ringbacks reconhecidos'
);

verify(
    countEvents($events, 'ring.ended') === 6,
    'Seis finais de ringback reconhecidos'
);

$cadences = array_values(array_filter(
    $events,
    static fn(array $e): bool =>
        $e['type'] === 'ring.cadence_detected' &&
        abs(($e['period_ms'] ?? 0) - 5000) <= 100
));

verify(
    count($cadences) >= 1,
    'Cadência de 5000 ms identificada'
);

verify(
    countEvents($events, 'ring.interrupted') >= 1,
    'Interrupção do ringback reconhecida'
);

// Primeira locução começa aproximadamente em 31134 ms.
$firstSpeech = eventsBetween(
    $events,
    'speech.started',
    31134,
    31334
);

verify(
    count($firstSpeech) >= 1,
    'Primeira locução reconhecida em até 200 ms'
);

if ($firstSpeech) {
    printf(
        "Latência primeira locução: %d ms\n",
        $firstSpeech[0]['at_ms'] - 31134
    );
}

// Bip real: aproximadamente 33333 a 33482 ms.
// Inclui margem posterior para a confirmação acústica.
$falseSpeech = eventsBetween(
    $events,
    'speech.started',
    33300,
    33800
);

verify(
    count($falseSpeech) === 0,
    'Bip de 1400 Hz não classificado como fala'
);

// Segunda região de fala começa aproximadamente em 44418 ms.
$secondSpeech = eventsBetween(
    $events,
    'speech.started',
    44418,
    44618
);

verify(
    count($secondSpeech) >= 1,
    'Segunda locução reconhecida em até 200 ms'
);

echo "\n========== FREQUÊNCIAS SINTÉTICAS ==========\n";

foreach ([425.0, 426.35, 427.5, 430.0] as $hz) {
    // Dois pulsos de 1 segundo, separados por 4 segundos.
    $synthetic =
        tone($hz, 1000) .
        str_repeat("\0", 4000 * 16) .
        tone($hz, 1000) .
        str_repeat("\0", 1500 * 16);

    $test = analyze($synthetic);
    $testEvents = $test['events'];

    $ok =
        countEvents($testEvents, 'ring.started') === 2 &&
        countEvents($testEvents, 'ring.ended') === 2 &&
        countEvents($testEvents, 'ring.cadence_detected') >= 1 &&
        countEvents($testEvents, 'speech.started') === 0;

    verify(
        $ok,
        sprintf(
            '%.2f Hz: dois toques, cadência e nenhuma fala falsa',
            $hz
        )
    );
}

echo "\n========== RESULTADO FINAL ==========\n";

if ($failures === 0) {
    echo "TODAS AS VERIFICAÇÕES PASSARAM.\n";
    echo "Comportamento da correção acústica confirmado.\n";
    exit(0);
}

printf(
    "%d verificação(ões) falharam.\n",
    $failures
);

exit(1);
