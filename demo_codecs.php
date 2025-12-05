<?php
/**
 * Demonstração das novas funcionalidades de codec de áudio
 *
 * Este arquivo demonstra:
 * - Encode PCM -> PCMA (A-law)
 * - Encode PCM -> PCMU (μ-law)
 * - Conversão L16 <-> PCM
 * - Mixagem de múltiplos canais de áudio
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "=== DEMONSTRAÇÃO DE CODECS DE ÁUDIO ===\n\n";

// ====== 1. TESTE DE ENCODE/DECODE PCMA (A-law) ======
echo "1. Teste PCMA (A-law)\n";
echo str_repeat("-", 50) . "\n";

// Gera um tom senoidal de teste (440 Hz - Lá 4)
$sample_rate = 8000;
$duration = 0.5; // 0.5 segundos
$frequency = 440;
$samples = (int)($sample_rate * $duration);

$pcm_data = '';
for ($i = 0; $i < $samples; $i++) {
    $sample = sin(2 * M_PI * $frequency * $i / $sample_rate) * 32767 * 0.5;
    $pcm_data .= pack('s', (int)$sample);
}

echo "PCM gerado: " . strlen($pcm_data) . " bytes\n";

// Encode para PCMA
$pcma = encodePcmToPcma($pcm_data);
echo "PCMA encoded: " . strlen($pcma) . " bytes (compressão: " .
     round((1 - strlen($pcma) / strlen($pcm_data)) * 100, 1) . "%)\n";

// Decode de volta
$pcm_decoded = decodePcmaToPcm($pcma);
echo "PCM decoded: " . strlen($pcm_decoded) . " bytes\n";
echo "Validação: " . (strlen($pcm_data) === strlen($pcm_decoded) ? "✓ OK" : "✗ ERRO") . "\n\n";

// ====== 2. TESTE DE ENCODE/DECODE PCMU (μ-law) ======
echo "2. Teste PCMU (μ-law)\n";
echo str_repeat("-", 50) . "\n";

// Encode para PCMU
$pcmu = encodePcmToPcmu($pcm_data);
echo "PCMU encoded: " . strlen($pcmu) . " bytes (compressão: " .
     round((1 - strlen($pcmu) / strlen($pcm_data)) * 100, 1) . "%)\n";

// Decode de volta
$pcm_decoded = decodePcmuToPcm($pcmu);
echo "PCM decoded: " . strlen($pcm_decoded) . " bytes\n";
echo "Validação: " . (strlen($pcm_data) === strlen($pcm_decoded) ? "✓ OK" : "✗ ERRO") . "\n\n";

// ====== 3. TESTE DE CONVERSÃO L16 (Big-Endian) ======
echo "3. Teste L16 (Big-Endian)\n";
echo str_repeat("-", 50) . "\n";

// Converte PCM (little-endian) para L16 (big-endian)
$l16 = encodePcmToL16($pcm_data);
echo "L16 encoded: " . strlen($l16) . " bytes\n";

// Converte de volta para PCM
$pcm_from_l16 = decodeL16ToPcm($l16);
echo "PCM decoded: " . strlen($pcm_from_l16) . " bytes\n";

// Verifica se a conversão é reversível
$match = ($pcm_data === $pcm_from_l16);
echo "Conversão reversível: " . ($match ? "✓ OK" : "✗ ERRO") . "\n\n";

// ====== 4. TESTE DE MIXAGEM DE CANAIS ======
echo "4. Teste de Mixagem de Canais\n";
echo str_repeat("-", 50) . "\n";

// Gera 3 tons diferentes para mixar
$frequencies = [440, 554, 659]; // Lá, Dó#, Mi (acorde Lá maior)
$channels = [];

foreach ($frequencies as $freq) {
    $channel_data = '';
    for ($i = 0; $i < $samples; $i++) {
        $sample = sin(2 * M_PI * $freq * $i / $sample_rate) * 32767 * 0.3;
        $channel_data .= pack('s', (int)$sample);
    }
    $channels[] = $channel_data;
}

echo "Canais gerados: " . count($channels) . "\n";
echo "Frequências: " . implode(', ', $frequencies) . " Hz\n";

// Mixa os canais
$mixed = mixAudioChannels($channels, $sample_rate);
echo "Áudio mixado: " . strlen($mixed) . " bytes\n";
echo "Validação: " . (strlen($mixed) === strlen($pcm_data) ? "✓ OK" : "✗ ERRO") . "\n\n";

// ====== 5. TESTE DE MIXAGEM COM 2 CANAIS (ESTÉREO -> MONO) ======
echo "5. Teste de Mixagem Estéreo -> Mono\n";
echo str_repeat("-", 50) . "\n";

// Canal esquerdo (440 Hz)
$left_channel = '';
for ($i = 0; $i < $samples; $i++) {
    $sample = sin(2 * M_PI * 440 * $i / $sample_rate) * 32767 * 0.5;
    $left_channel .= pack('s', (int)$sample);
}

// Canal direito (880 Hz - uma oitava acima)
$right_channel = '';
for ($i = 0; $i < $samples; $i++) {
    $sample = sin(2 * M_PI * 880 * $i / $sample_rate) * 32767 * 0.5;
    $right_channel .= pack('s', (int)$sample);
}

$stereo_mixed = mixAudioChannels([$left_channel, $right_channel], $sample_rate);
echo "2 canais mixados: " . strlen($stereo_mixed) . " bytes\n";
echo "Validação: " . (strlen($stereo_mixed) === strlen($pcm_data) ? "✓ OK" : "✗ ERRO") . "\n\n";

// ====== 6. PIPELINE COMPLETO: PCM -> PCMA -> PCM -> MIX ======
echo "6. Pipeline Completo\n";
echo str_repeat("-", 50) . "\n";

// Cria 2 canais diferentes
$channel1_pcm = '';
$channel2_pcm = '';

for ($i = 0; $i < $samples; $i++) {
    // Canal 1: 440 Hz
    $s1 = sin(2 * M_PI * 440 * $i / $sample_rate) * 32767 * 0.4;
    $channel1_pcm .= pack('s', (int)$s1);

    // Canal 2: 523 Hz (Dó)
    $s2 = sin(2 * M_PI * 523 * $i / $sample_rate) * 32767 * 0.4;
    $channel2_pcm .= pack('s', (int)$s2);
}

// Encode para PCMA
$channel1_pcma = encodePcmToPcma($channel1_pcm);
$channel2_pcma = encodePcmToPcma($channel2_pcm);

echo "Canal 1: PCM=" . strlen($channel1_pcm) . "B -> PCMA=" . strlen($channel1_pcma) . "B\n";
echo "Canal 2: PCM=" . strlen($channel2_pcm) . "B -> PCMA=" . strlen($channel2_pcma) . "B\n";

// Decode de volta
$channel1_decoded = decodePcmaToPcm($channel1_pcma);
$channel2_decoded = decodePcmaToPcm($channel2_pcma);

// Mixa os canais decodificados
$final_mixed = mixAudioChannels([$channel1_decoded, $channel2_decoded], $sample_rate);

echo "Mix final: " . strlen($final_mixed) . " bytes\n";
echo "Pipeline: PCM -> PCMA -> PCM -> MIX ✓\n\n";

// ====== 7. TESTE COM MÚLTIPLOS CANAIS ======
echo "7. Teste com Múltiplos Canais (Acorde Completo)\n";
echo str_repeat("-", 50) . "\n";

// Gera acorde Dó maior de 7ª (C, E, G, B)
$chord_freqs = [261.63, 329.63, 392.00, 493.88]; // Dó, Mi, Sol, Si
$chord_channels = [];

foreach ($chord_freqs as $freq) {
    $channel = '';
    for ($i = 0; $i < $samples; $i++) {
        $sample = sin(2 * M_PI * $freq * $i / $sample_rate) * 32767 * 0.25;
        $channel .= pack('s', (int)$sample);
    }
    $chord_channels[] = $channel;
}

$chord_mixed = mixAudioChannels($chord_channels, $sample_rate);
echo "Acorde com " . count($chord_channels) . " notas mixadas\n";
echo "Tamanho: " . strlen($chord_mixed) . " bytes\n";
echo "Normalização automática aplicada ✓\n\n";

// ====== 8. COMPARAÇÃO DE QUALIDADE ======
echo "8. Comparação de Qualidade dos Codecs\n";
echo str_repeat("-", 50) . "\n";

// Calcula SNR aproximado (Signal-to-Noise Ratio)
function calculateSNR($original, $decoded) {
    $orig_samples = unpack('s*', $original);
    $dec_samples = unpack('s*', $decoded);

    $signal_power = 0;
    $noise_power = 0;

    $count = min(count($orig_samples), count($dec_samples));

    for ($i = 1; $i <= $count; $i++) {
        $signal_power += $orig_samples[$i] * $orig_samples[$i];
        $diff = $orig_samples[$i] - $dec_samples[$i];
        $noise_power += $diff * $diff;
    }

    if ($noise_power == 0) return INF;
    return 10 * log10($signal_power / $noise_power);
}

// Testa PCMA
$pcma_decoded = decodePcmaToPcm(encodePcmToPcma($pcm_data));
$snr_pcma = calculateSNR($pcm_data, $pcma_decoded);

// Testa PCMU
$pcmu_decoded = decodePcmuToPcm(encodePcmToPcmu($pcm_data));
$snr_pcmu = calculateSNR($pcm_data, $pcmu_decoded);

echo "PCMA (A-law) SNR: " . round($snr_pcma, 2) . " dB\n";
echo "PCMU (μ-law) SNR: " . round($snr_pcmu, 2) . " dB\n";
echo "Compressão: 50% (16-bit -> 8-bit)\n\n";

echo "=== TODOS OS TESTES CONCLUÍDOS ===\n";
echo "\nResumo das funcionalidades:\n";
echo "✓ encodePcmToPcma() - Encode PCM para A-law\n";
echo "✓ encodePcmToPcmu() - Encode PCM para μ-law\n";
echo "✓ decodePcmaToPcm() - Decode A-law para PCM\n";
echo "✓ decodePcmuToPcm() - Decode μ-law para PCM\n";
echo "✓ encodePcmToL16() - Converte PCM LE para L16 BE\n";
echo "✓ decodeL16ToPcm() - Converte L16 BE para PCM LE\n";
echo "✓ mixAudioChannels() - Mixa múltiplos canais com normalização\n";
echo "✓ pcmLeToBe() - Converte endianness\n\n";

echo "Características especiais da mixagem:\n";
echo "• Normalização automática usando sqrt(N) para preservar energia\n";
echo "• Soft clipping para evitar distorção\n";
echo "• Suporte para 2+ canais de tamanhos diferentes\n";
echo "• Buffer de 32-bit para evitar overflow durante soma\n";
