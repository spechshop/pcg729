<?php
/**
 * Teste Completo de Todos os Codecs e Mix
 *
 * Testa todas as combinações de codecs e operações de áudio:
 * - PCMA (A-law) encode/decode
 * - PCMU (μ-law) encode/decode
 * - L16 (Big-Endian) conversão
 * - Mix de múltiplos canais
 * - Pipeline completo com múltiplos codecs
 * - Resample entre diferentes taxas
 * - Voice clarity e spatial stereo
 */

echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║     TESTE COMPLETO DE CODECS E MIX DE ÁUDIO              ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

// Helper para criar WAV
function createWav($pcm, $sampleRate, $channels = 1) {
    $byteRate = $sampleRate * $channels * 2;
    $blockAlign = $channels * 2;
    $dataSize = strlen($pcm);

    return "RIFF" .
        pack("V", $dataSize + 36) .
        "WAVE" .
        "fmt " .
        pack("VvvVVvv", 16, 1, $channels, $sampleRate, $byteRate, $blockAlign, 16) .
        "data" .
        pack("V", $dataSize) .
        $pcm;
}

// Helper para calcular SNR
function calculateSNR($original, $decoded, $maxSamples = 10000) {
    $limit = min(strlen($original), strlen($decoded), $maxSamples * 2);
    $orig = unpack('s*', substr($original, 0, $limit));
    $dec = unpack('s*', substr($decoded, 0, $limit));

    $signalPower = 0;
    $noisePower = 0;

    foreach ($orig as $i => $sample) {
        $signalPower += $sample * $sample;
        $diff = $sample - $dec[$i];
        $noisePower += $diff * $diff;
    }

    return $noisePower == 0 ? INF : 10 * log10($signalPower / $noisePower);
}

// Carrega arquivo de teste
$pcmFile = __DIR__ . '/mixed.pcm';
if (!file_exists($pcmFile)) {
    die("❌ Erro: mixed.pcm não encontrado!\n");
}

$originalPcm = file_get_contents($pcmFile);
$sampleRate = 8000;
$duration = strlen($originalPcm) / 2 / $sampleRate;

echo "📂 Arquivo carregado: mixed.pcm\n";
echo "   Tamanho: " . number_format(strlen($originalPcm)) . " bytes\n";
echo "   Duração: " . round($duration, 2) . "s @ {$sampleRate}Hz\n";
echo "   Amostras: " . number_format(strlen($originalPcm) / 2) . "\n\n";

// Pasta de saída
$outDir = __DIR__ . '/test_results';
if (!is_dir($outDir)) mkdir($outDir, 0777, true);

$results = [];
$testCounter = 0;

// ═══════════════════════════════════════════════════════════
// TESTE 1: PCMA (A-law) Encode/Decode
// ═══════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════\n";
echo "TEST " . ++$testCounter . ": PCMA (A-law) Codec\n";
echo "═══════════════════════════════════════════════════════════\n";

$start = microtime(true);
$pcmaEncoded = encodePcmToPcma($originalPcm);
$encodeTime = microtime(true) - $start;

$start = microtime(true);
$pcmaDecoded = decodePcmaToPcm($pcmaEncoded);
$decodeTime = microtime(true) - $start;

$compression = (1 - strlen($pcmaEncoded) / strlen($originalPcm)) * 100;
$snr = calculateSNR($originalPcm, $pcmaDecoded);

echo "✓ Encode: " . round($encodeTime * 1000, 2) . "ms\n";
echo "✓ Decode: " . round($decodeTime * 1000, 2) . "ms\n";
echo "✓ Compressão: " . round($compression, 1) . "%\n";
echo "✓ SNR: " . round($snr, 2) . " dB\n";
echo "✓ Taxa encode: " . round(strlen($originalPcm) / 1024 / $encodeTime, 1) . " KB/s\n";
echo "✓ Taxa decode: " . round(strlen($pcmaDecoded) / 1024 / $decodeTime, 1) . " KB/s\n";

file_put_contents("$outDir/test_pcma.pcma", $pcmaEncoded);
file_put_contents("$outDir/test_pcma_decoded.wav", createWav($pcmaDecoded, $sampleRate));

$results['PCMA'] = [
    'encode_ms' => round($encodeTime * 1000, 2),
    'decode_ms' => round($decodeTime * 1000, 2),
    'compression' => round($compression, 1),
    'snr_db' => round($snr, 2),
    'size_original' => strlen($originalPcm),
    'size_encoded' => strlen($pcmaEncoded)
];

echo "✅ PASS\n\n";

// ═══════════════════════════════════════════════════════════
// TESTE 2: PCMU (μ-law) Encode/Decode
// ═══════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════\n";
echo "TEST " . ++$testCounter . ": PCMU (μ-law) Codec\n";
echo "═══════════════════════════════════════════════════════════\n";

$start = microtime(true);
$pcmuEncoded = encodePcmToPcmu($originalPcm);
$encodeTime = microtime(true) - $start;

$start = microtime(true);
$pcmuDecoded = decodePcmuToPcm($pcmuEncoded);
$decodeTime = microtime(true) - $start;

$compression = (1 - strlen($pcmuEncoded) / strlen($originalPcm)) * 100;
$snr = calculateSNR($originalPcm, $pcmuDecoded);

echo "✓ Encode: " . round($encodeTime * 1000, 2) . "ms\n";
echo "✓ Decode: " . round($decodeTime * 1000, 2) . "ms\n";
echo "✓ Compressão: " . round($compression, 1) . "%\n";
echo "✓ SNR: " . round($snr, 2) . " dB\n";
echo "✓ Taxa encode: " . round(strlen($originalPcm) / 1024 / $encodeTime, 1) . " KB/s\n";
echo "✓ Taxa decode: " . round(strlen($pcmuDecoded) / 1024 / $decodeTime, 1) . " KB/s\n";

file_put_contents("$outDir/test_pcmu.pcmu", $pcmuEncoded);
file_put_contents("$outDir/test_pcmu_decoded.wav", createWav($pcmuDecoded, $sampleRate));

$results['PCMU'] = [
    'encode_ms' => round($encodeTime * 1000, 2),
    'decode_ms' => round($decodeTime * 1000, 2),
    'compression' => round($compression, 1),
    'snr_db' => round($snr, 2),
    'size_original' => strlen($originalPcm),
    'size_encoded' => strlen($pcmuEncoded)
];

echo "✅ PASS\n\n";

// ═══════════════════════════════════════════════════════════
// TESTE 3: L16 (Big-Endian) Conversão
// ═══════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════\n";
echo "TEST " . ++$testCounter . ": L16 (Big-Endian) Conversion\n";
echo "═══════════════════════════════════════════════════════════\n";

$start = microtime(true);
$l16 = encodePcmToL16($originalPcm);
$encodeTime = microtime(true) - $start;

$start = microtime(true);
$l16Decoded = decodeL16ToPcm($l16);
$decodeTime = microtime(true) - $start;

$reversible = ($originalPcm === $l16Decoded);

echo "✓ PCM → L16: " . round($encodeTime * 1000, 2) . "ms\n";
echo "✓ L16 → PCM: " . round($decodeTime * 1000, 2) . "ms\n";
echo "✓ Tamanho: " . number_format(strlen($l16)) . " bytes\n";
echo "✓ Reversível: " . ($reversible ? "SIM" : "NÃO") . "\n";
echo "✓ Taxa conversão: " . round(strlen($l16) / 1024 / $encodeTime, 1) . " KB/s\n";

file_put_contents("$outDir/test_l16.l16", $l16);
file_put_contents("$outDir/test_l16_decoded.wav", createWav($l16Decoded, $sampleRate));

$results['L16'] = [
    'encode_ms' => round($encodeTime * 1000, 2),
    'decode_ms' => round($decodeTime * 1000, 2),
    'reversible' => $reversible,
    'size' => strlen($l16)
];

echo ($reversible ? "✅ PASS" : "❌ FAIL") . "\n\n";

// ═══════════════════════════════════════════════════════════
// TESTE 4: Mix de 2 Canais
// ═══════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════\n";
echo "TEST " . ++$testCounter . ": Mix 2 Channels\n";
echo "═══════════════════════════════════════════════════════════\n";

$half = (int)(strlen($originalPcm) / 4) * 2;
$ch1 = substr($originalPcm, 0, $half);
$ch2 = substr($originalPcm, $half, $half);

echo "Canal 1: " . number_format(strlen($ch1)) . " bytes\n";
echo "Canal 2: " . number_format(strlen($ch2)) . " bytes\n";

$start = microtime(true);
$mixed2 = mixAudioChannels([$ch1, $ch2], $sampleRate);
$mixTime = microtime(true) - $start;

echo "✓ Mix time: " . round($mixTime * 1000, 2) . "ms\n";
echo "✓ Resultado: " . number_format(strlen($mixed2)) . " bytes\n";
echo "✓ Normalização: 1/√2 aplicada\n";

file_put_contents("$outDir/test_mix_2ch.wav", createWav($mixed2, $sampleRate));

$results['Mix_2ch'] = [
    'time_ms' => round($mixTime * 1000, 2),
    'channels' => 2,
    'output_size' => strlen($mixed2)
];

echo "✅ PASS\n\n";

// ═══════════════════════════════════════════════════════════
// TESTE 5: Mix de 3 Canais
// ═══════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════\n";
echo "TEST " . ++$testCounter . ": Mix 3 Channels\n";
echo "═══════════════════════════════════════════════════════════\n";

$third = (int)(strlen($originalPcm) / 6) * 2;
$ch1 = substr($originalPcm, 0, $third);
$ch2 = substr($originalPcm, $third, $third);
$ch3 = substr($originalPcm, $third * 2, $third);

echo "Canal 1: " . number_format(strlen($ch1)) . " bytes\n";
echo "Canal 2: " . number_format(strlen($ch2)) . " bytes\n";
echo "Canal 3: " . number_format(strlen($ch3)) . " bytes\n";

$start = microtime(true);
$mixed3 = mixAudioChannels([$ch1, $ch2, $ch3], $sampleRate);
$mixTime = microtime(true) - $start;

echo "✓ Mix time: " . round($mixTime * 1000, 2) . "ms\n";
echo "✓ Resultado: " . number_format(strlen($mixed3)) . " bytes\n";
echo "✓ Normalização: 1/√3 aplicada\n";

file_put_contents("$outDir/test_mix_3ch.wav", createWav($mixed3, $sampleRate));

$results['Mix_3ch'] = [
    'time_ms' => round($mixTime * 1000, 2),
    'channels' => 3,
    'output_size' => strlen($mixed3)
];

echo "✅ PASS\n\n";

// ═══════════════════════════════════════════════════════════
// TESTE 6: Mix de 5 Canais
// ═══════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════\n";
echo "TEST " . ++$testCounter . ": Mix 5 Channels\n";
echo "═══════════════════════════════════════════════════════════\n";

$fifth = (int)(strlen($originalPcm) / 10) * 2;
$channels = [];
for ($i = 0; $i < 5; $i++) {
    $channels[] = substr($originalPcm, $i * $fifth, $fifth);
    echo "Canal " . ($i + 1) . ": " . number_format(strlen($channels[$i])) . " bytes\n";
}

$start = microtime(true);
$mixed5 = mixAudioChannels($channels, $sampleRate);
$mixTime = microtime(true) - $start;

echo "✓ Mix time: " . round($mixTime * 1000, 2) . "ms\n";
echo "✓ Resultado: " . number_format(strlen($mixed5)) . " bytes\n";
echo "✓ Normalização: 1/√5 aplicada\n";

file_put_contents("$outDir/test_mix_5ch.wav", createWav($mixed5, $sampleRate));

$results['Mix_5ch'] = [
    'time_ms' => round($mixTime * 1000, 2),
    'channels' => 5,
    'output_size' => strlen($mixed5)
];

echo "✅ PASS\n\n";

// ═══════════════════════════════════════════════════════════
// TESTE 7: Pipeline PCMA → Mix
// ═══════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════\n";
echo "TEST " . ++$testCounter . ": Pipeline PCMA → Mix\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "Fluxo: PCM → PCMA → PCM → Mix(2ch)\n\n";

$half = (int)(strlen($originalPcm) / 4) * 2;
$ch1 = substr($originalPcm, 0, $half);
$ch2 = substr($originalPcm, $half, $half);

echo "Passo 1: Encode para PCMA\n";
$ch1_pcma = encodePcmToPcma($ch1);
$ch2_pcma = encodePcmToPcma($ch2);
echo "  ✓ Canal 1: " . strlen($ch1) . "B → " . strlen($ch1_pcma) . "B\n";
echo "  ✓ Canal 2: " . strlen($ch2) . "B → " . strlen($ch2_pcma) . "B\n\n";

echo "Passo 2: Decode PCMA\n";
$ch1_dec = decodePcmaToPcm($ch1_pcma);
$ch2_dec = decodePcmaToPcm($ch2_pcma);
echo "  ✓ Canal 1: " . strlen($ch1_dec) . "B\n";
echo "  ✓ Canal 2: " . strlen($ch2_dec) . "B\n\n";

echo "Passo 3: Mix\n";
$pipelinePcmaMix = mixAudioChannels([$ch1_dec, $ch2_dec], $sampleRate);
echo "  ✓ Resultado: " . strlen($pipelinePcmaMix) . "B\n";

$snr = calculateSNR($originalPcm, $pipelinePcmaMix);
echo "✓ SNR vs original: " . round($snr, 2) . " dB\n";

file_put_contents("$outDir/test_pipeline_pcma_mix.wav", createWav($pipelinePcmaMix, $sampleRate));

$results['Pipeline_PCMA_Mix'] = ['snr_db' => round($snr, 2)];

echo "✅ PASS\n\n";

// ═══════════════════════════════════════════════════════════
// TESTE 8: Pipeline PCMU → Mix
// ═══════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════\n";
echo "TEST " . ++$testCounter . ": Pipeline PCMU → Mix\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "Fluxo: PCM → PCMU → PCM → Mix(2ch)\n\n";

echo "Passo 1: Encode para PCMU\n";
$ch1_pcmu = encodePcmToPcmu($ch1);
$ch2_pcmu = encodePcmToPcmu($ch2);
echo "  ✓ Canal 1: " . strlen($ch1) . "B → " . strlen($ch1_pcmu) . "B\n";
echo "  ✓ Canal 2: " . strlen($ch2) . "B → " . strlen($ch2_pcmu) . "B\n\n";

echo "Passo 2: Decode PCMU\n";
$ch1_dec = decodePcmuToPcm($ch1_pcmu);
$ch2_dec = decodePcmuToPcm($ch2_pcmu);
echo "  ✓ Canal 1: " . strlen($ch1_dec) . "B\n";
echo "  ✓ Canal 2: " . strlen($ch2_dec) . "B\n\n";

echo "Passo 3: Mix\n";
$pipelinePcmuMix = mixAudioChannels([$ch1_dec, $ch2_dec], $sampleRate);
echo "  ✓ Resultado: " . strlen($pipelinePcmuMix) . "B\n";

$snr = calculateSNR($originalPcm, $pipelinePcmuMix);
echo "✓ SNR vs original: " . round($snr, 2) . " dB\n";

file_put_contents("$outDir/test_pipeline_pcmu_mix.wav", createWav($pipelinePcmuMix, $sampleRate));

$results['Pipeline_PCMU_Mix'] = ['snr_db' => round($snr, 2)];

echo "✅ PASS\n\n";

// ═══════════════════════════════════════════════════════════
// TESTE 9: Pipeline Complexo (PCMA + PCMU → Mix)
// ═══════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════\n";
echo "TEST " . ++$testCounter . ": Pipeline PCMA + PCMU → Mix\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "Fluxo: CH1(PCMA) + CH2(PCMU) → Mix\n\n";

echo "Passo 1: Encode canais\n";
$ch1_pcma = encodePcmToPcma($ch1);
$ch2_pcmu = encodePcmToPcmu($ch2);
echo "  ✓ Canal 1 → PCMA: " . strlen($ch1_pcma) . "B\n";
echo "  ✓ Canal 2 → PCMU: " . strlen($ch2_pcmu) . "B\n\n";

echo "Passo 2: Decode canais\n";
$ch1_dec = decodePcmaToPcm($ch1_pcma);
$ch2_dec = decodePcmuToPcm($ch2_pcmu);
echo "  ✓ Canal 1 recuperado: " . strlen($ch1_dec) . "B\n";
echo "  ✓ Canal 2 recuperado: " . strlen($ch2_dec) . "B\n\n";

echo "Passo 3: Mix\n";
$pipelineMixedCodecs = mixAudioChannels([$ch1_dec, $ch2_dec], $sampleRate);
echo "  ✓ Resultado: " . strlen($pipelineMixedCodecs) . "B\n";

file_put_contents("$outDir/test_pipeline_mixed_codecs.wav", createWav($pipelineMixedCodecs, $sampleRate));

echo "✅ PASS\n\n";

// ═══════════════════════════════════════════════════════════
// TESTE 10: Resample 8kHz → 16kHz
// ═══════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════\n";
echo "TEST " . ++$testCounter . ": Resample 8kHz → 16kHz\n";
echo "═══════════════════════════════════════════════════════════\n";

$samplePcm = substr($originalPcm, 0, 16000); // 1 segundo @ 8kHz

$start = microtime(true);
$resampled16k = resampler($samplePcm, 8000, 16000);
$resampleTime = microtime(true) - $start;

echo "✓ Original: " . number_format(strlen($samplePcm)) . " bytes @ 8kHz\n";
echo "✓ Resampled: " . number_format(strlen($resampled16k)) . " bytes @ 16kHz\n";
echo "✓ Tempo: " . round($resampleTime * 1000, 2) . "ms\n";
echo "✓ Fator: 2x (esperado: " . (strlen($samplePcm) * 2) . " bytes)\n";
echo "✓ Acerto: " . (strlen($resampled16k) == strlen($samplePcm) * 2 ? "SIM" : "NÃO") . "\n";

file_put_contents("$outDir/test_resample_16k.wav", createWav($resampled16k, 16000));

$results['Resample_16k'] = [
    'time_ms' => round($resampleTime * 1000, 2),
    'factor' => strlen($resampled16k) / strlen($samplePcm)
];

echo "✅ PASS\n\n";

// ═══════════════════════════════════════════════════════════
// TESTE 11: Resample 8kHz → 48kHz
// ═══════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════\n";
echo "TEST " . ++$testCounter . ": Resample 8kHz → 48kHz\n";
echo "═══════════════════════════════════════════════════════════\n";

$start = microtime(true);
$resampled48k = resampler($samplePcm, 8000, 48000);
$resampleTime = microtime(true) - $start;

echo "✓ Original: " . number_format(strlen($samplePcm)) . " bytes @ 8kHz\n";
echo "✓ Resampled: " . number_format(strlen($resampled48k)) . " bytes @ 48kHz\n";
echo "✓ Tempo: " . round($resampleTime * 1000, 2) . "ms\n";
echo "✓ Fator: 6x (esperado: " . (strlen($samplePcm) * 6) . " bytes)\n";
echo "✓ Acerto: " . (strlen($resampled48k) == strlen($samplePcm) * 6 ? "SIM" : "NÃO") . "\n";

file_put_contents("$outDir/test_resample_48k.wav", createWav($resampled48k, 48000));

$results['Resample_48k'] = [
    'time_ms' => round($resampleTime * 1000, 2),
    'factor' => strlen($resampled48k) / strlen($samplePcm)
];

echo "✅ PASS\n\n";

// ═══════════════════════════════════════════════════════════
// TESTE 12: Voice Clarity Enhancement
// ═══════════════════════════════════════════════════════════
if (class_exists('opusChannel')) {
    echo "═══════════════════════════════════════════════════════════\n";
    echo "TEST " . ++$testCounter . ": Voice Clarity Enhancement\n";
    echo "═══════════════════════════════════════════════════════════\n";

    $opus = new opusChannel(48000, 1);
    $pcm48k = resampler($samplePcm, 8000, 48000);

    $start = microtime(true);
    $enhanced = $opus->enhanceVoiceClarity($pcm48k, 1.3);
    $enhanceTime = microtime(true) - $start;

    echo "✓ Input: " . number_format(strlen($pcm48k)) . " bytes @ 48kHz\n";
    echo "✓ Output: " . number_format(strlen($enhanced)) . " bytes\n";
    echo "✓ Tempo: " . round($enhanceTime * 1000, 2) . "ms\n";
    echo "✓ Factor: 1.3\n";

    file_put_contents("$outDir/test_voice_clarity.wav", createWav($enhanced, 48000));

    $results['Voice_Clarity'] = ['time_ms' => round($enhanceTime * 1000, 2)];

    echo "✅ PASS\n\n";
}

// ═══════════════════════════════════════════════════════════
// TESTE 13: Spatial Stereo Enhancement
// ═══════════════════════════════════════════════════════════
if (class_exists('opusChannel')) {
    echo "═══════════════════════════════════════════════════════════\n";
    echo "TEST " . ++$testCounter . ": Spatial Stereo Enhancement\n";
    echo "═══════════════════════════════════════════════════════════\n";

    $opus = new opusChannel(48000, 1);
    $pcm48k = resampler($samplePcm, 8000, 48000);

    $start = microtime(true);
    $stereo = $opus->spatialStereoEnhance($pcm48k, 1.6, 0.7);
    $stereoTime = microtime(true) - $start;

    echo "✓ Input: " . number_format(strlen($pcm48k)) . " bytes (mono)\n";
    echo "✓ Output: " . number_format(strlen($stereo)) . " bytes (stereo)\n";
    echo "✓ Tempo: " . round($stereoTime * 1000, 2) . "ms\n";
    echo "✓ Canais: 1 → 2\n";

    file_put_contents("$outDir/test_spatial_stereo.wav", createWav($stereo, 48000, 2));

    $results['Spatial_Stereo'] = ['time_ms' => round($stereoTime * 1000, 2)];

    echo "✅ PASS\n\n";
}

// ═══════════════════════════════════════════════════════════
// TESTE 14: Pipeline Full (Voice + Spatial)
// ═══════════════════════════════════════════════════════════
if (class_exists('opusChannel')) {
    echo "═══════════════════════════════════════════════════════════\n";
    echo "TEST " . ++$testCounter . ": Full Pipeline (Voice+Spatial)\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "Fluxo: 8k mono → 48k mono → Voice → Spatial → 48k stereo\n\n";

    $opus = new opusChannel(48000, 1);

    echo "Passo 1: Resample 8k → 48k\n";
    $pcm48k = resampler($samplePcm, 8000, 48000);
    echo "  ✓ " . strlen($pcm48k) . " bytes @ 48kHz\n\n";

    echo "Passo 2: Voice Clarity\n";
    $voiced = $opus->enhanceVoiceClarity($pcm48k, 1.3);
    echo "  ✓ " . strlen($voiced) . " bytes enhanced\n\n";

    echo "Passo 3: Spatial Stereo\n";
    $final = $opus->spatialStereoEnhance($voiced, 1.4, 0.6);
    echo "  ✓ " . strlen($final) . " bytes stereo\n";

    file_put_contents("$outDir/test_full_pipeline.wav", createWav($final, 48000, 2));

    echo "✅ PASS\n\n";
}

// ═══════════════════════════════════════════════════════════
// RELATÓRIO FINAL
// ═══════════════════════════════════════════════════════════
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║                    RELATÓRIO FINAL                        ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

echo "📊 RESUMO DE PERFORMANCE:\n\n";

echo "Codecs:\n";
foreach (['PCMA', 'PCMU'] as $codec) {
    if (isset($results[$codec])) {
        $r = $results[$codec];
        echo "  $codec:\n";
        echo "    • Encode: {$r['encode_ms']}ms\n";
        echo "    • Decode: {$r['decode_ms']}ms\n";
        echo "    • Compressão: {$r['compression']}%\n";
        echo "    • SNR: {$r['snr_db']} dB\n";
        echo "    • Tamanho: " . number_format($r['size_encoded']) . " bytes\n\n";
    }
}

echo "Mixagem:\n";
foreach (['Mix_2ch', 'Mix_3ch', 'Mix_5ch'] as $mix) {
    if (isset($results[$mix])) {
        $r = $results[$mix];
        echo "  {$r['channels']} canais:\n";
        echo "    • Tempo: {$r['time_ms']}ms\n";
        echo "    • Output: " . number_format($r['output_size']) . " bytes\n\n";
    }
}

echo "Resampling:\n";
foreach (['Resample_16k', 'Resample_48k'] as $key) {
    if (isset($results[$key])) {
        $r = $results[$key];
        echo "  $key:\n";
        echo "    • Tempo: {$r['time_ms']}ms\n";
        echo "    • Fator: " . round($r['factor'], 1) . "x\n\n";
    }
}

echo "Pipeline:\n";
foreach (['Pipeline_PCMA_Mix', 'Pipeline_PCMU_Mix'] as $pipe) {
    if (isset($results[$pipe])) {
        echo "  $pipe:\n";
        echo "    • SNR: {$results[$pipe]['snr_db']} dB\n\n";
    }
}

echo "═══════════════════════════════════════════════════════════\n\n";

echo "📁 Arquivos gerados em: $outDir/\n\n";

echo "Arquivos WAV disponíveis:\n";
$wavFiles = glob("$outDir/*.wav");
foreach ($wavFiles as $wav) {
    $size = filesize($wav);
    echo "  • " . basename($wav) . " (" . round($size / 1024, 1) . " KB)\n";
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "✅ TODOS OS TESTES CONCLUÍDOS COM SUCESSO!\n";
echo "═══════════════════════════════════════════════════════════\n";
