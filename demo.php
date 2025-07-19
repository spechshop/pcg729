<?php


function generateWavHeaderPcm(int $dataLength, int $sampleRate = 8000, int $channels = 1): string
{
    return pack('A4V', 'RIFF', 36 + $dataLength)
        . 'WAVE'
        . pack('A4VvvVVvv', 'fmt ', 16, 1, $channels, $sampleRate, $sampleRate * $channels * 2, $channels * 2, 16)
        . pack('A4V', 'data', $dataLength);
}

function readWavPcmData(string $filename): string
{
    $data = file_get_contents($filename);
    if (!$data || strlen($data) <= 44) {
        die("Arquivo WAV inválido.\n");
    }
    return substr($data, 44); // Pula o cabeçalho WAV
}

$inputWav = 'e9da2774defd2bf6-mixed.wav';
$outputG729 = 'convertido_g729.raw';
$outputWavDecoded = 'convertido.wav';

// === 1. Ler PCM do WAV ===
$pcmData = readWavPcmData($inputWav);

// === 2. Criar canal G.729 ===
$chan = new bcg729Channel();

// === 3. Codificar para G.729 ===
$output = '';
for ($i = 0; $i + 320 <= strlen($pcmData); $i += 320) { // 20ms frames
    $frame = substr($pcmData, $i, 320);
    $g729 = $chan->encode($frame);
    $output .= $g729;
}

file_put_contents($outputG729, $output);
echo "✅ Arquivo G.729 gerado: $outputG729\n";

// === 4. Decodificar de volta para PCM ===
$g729Data = file_get_contents($outputG729);
$pcmDecoded = '';

for ($i = 0; $i + 10 <= strlen($g729Data); $i += 10) { // Cada frame G.729 tem 10 bytes
    $frame = substr($g729Data, $i, 10);
    $pcmFrame = $chan->decode($frame);
    $pcmDecoded .= $pcmFrame;
}

// === 5. Salvar o WAV reconstruído ===
$wav = generateWavHeaderPcm(strlen($pcmDecoded)) . $pcmDecoded;
file_put_contents($outputWavDecoded, $wav);
echo "✅ WAV decodificado gerado: $outputWavDecoded\n";

$chan->close();
