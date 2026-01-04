<?php

if (!extension_loaded('opus')) {
    die("Extensão opus não carregada\n");
}

$inputFile = './48000.pcm';
if (!file_exists($inputFile)) {
    die("Arquivo 48000.pcm não encontrado\n");
}

/* ================================
 * Util: split PCM em frames Opus válidos
 * ================================ */
function splitPcmFrames(string $pcm, int $rate, int $frameMs = 20, int $channels = 1): array
{
    $samplesPerFrame = (int)(($rate * $frameMs) / 1000);
    $bytesPerFrame   = $samplesPerFrame * 2 * $channels;

    $frames = [];
    $len = strlen($pcm);

    for ($i = 0; $i + $bytesPerFrame <= $len; $i += $bytesPerFrame) {
        $frames[] = substr($pcm, $i, $bytesPerFrame);
    }

    return $frames;
}

/* ================================
 * Util: salvar WAV PCM16 mono
 * ================================ */
function saveWav(string $file, string $pcm, int $rate): void
{
    $dataSize = strlen($pcm);
    $riffSize = 36 + $dataSize;

    $hdr =
        "RIFF" . pack("V", $riffSize) . "WAVE" .
        "fmt " . pack("V", 16) .
        pack("v", 1) .
        pack("v", 1) .
        pack("V", $rate) .
        pack("V", $rate * 2) .
        pack("v", 2) .
        pack("v", 16) .
        "data" . pack("V", $dataSize);

    file_put_contents($file, $hdr . $pcm);
}

/* ================================
 * Início do teste
 * ================================ */

$opus = new opusChannel(48000, 1);

/* 1️⃣ Ler PCM original 48k */
$pcm48k = file_get_contents($inputFile);
saveWav("01_input_48k.wav", $pcm48k, 48000);

/* 2️⃣ Split em frames Opus válidos (20 ms @48k) */
$frames = splitPcmFrames($pcm48k, 48000, 20);

/* 3️⃣ Encode frame a frame */
$encodedPackets = [];
foreach ($frames as $f) {
    $encodedPackets[] = $opus->encode($f);
}

/* 4️⃣ Decode frame a frame */
$decoded48k = '';
foreach ($encodedPackets as $pkt) {
    $decoded48k .= $opus->decode($pkt);
}
saveWav("02_opus_roundtrip_48k.wav", $decoded48k, 48000);

/* 5️⃣ 48k → 8k (soxr) */
$pcm8k = $opus->resample($decoded48k, 48000, 8000);
saveWav("03_resampled_8k.wav", $pcm8k, 8000);

/* 6️⃣ 8k → 48k (round-trip de sample rate) */
$pcm48k_rt = $opus->resample($pcm8k, 8000, 48000);
saveWav("04_resample_roundtrip_48k.wav", $pcm48k_rt, 48000);

/* 7️⃣ Enhance Voice Clarity (opcional, só pra validar DSP) */
$enhanced = $opus->enhanceVoiceClarity($decoded48k, 1.2);
saveWav("05_enhanced_48k.wav", $enhanced, 48000);

/* ================================
 * Info final
 * ================================ */
echo "=== OpusChannel Info ===\n";
print_r($opus->getInfo());

echo "\nArquivos gerados:\n";
echo "01_input_48k.wav\n";
echo "02_opus_roundtrip_48k.wav\n";
echo "03_resampled_8k.wav\n";
echo "04_resample_roundtrip_48k.wav\n";
echo "05_enhanced_48k.wav\n";

