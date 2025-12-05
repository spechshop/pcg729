<?php

function pcmToWave(string $pcm, int $sampleRate = 48000, int $channels = 1): string
{
    $byteRate = $sampleRate * $channels * 2;
    $blockAlign = $channels * 2;
    $bitsPerSample = 16;
    $dataSize = strlen($pcm);
    $chunkSize = 36 + $dataSize;

    // monta cabeçalho RIFF/WAVE
    $header =
        "RIFF" .
        pack("V", $chunkSize) .
        "WAVE" .
        "fmt " .
        pack("VvvVVvv",
            16,             // Subchunk1Size
            1,              // AudioFormat (PCM)
            $channels,
            $sampleRate,
            $byteRate,
            $blockAlign,
            $bitsPerSample
        ) .
        "data" .
        pack("V", $dataSize);

    return $header . $pcm;
}

// ------------------------------------------------------------
// CONFIG
$inputPcm      = 'mixed.pcm';
$sampleInRate  = 8000;  // seu mixed.pcm está em 8k
$inChannels    = 1;

// pasta de saída
$outDir = __DIR__ . '/comparativo_audio';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

// carrega PCM original
$mixed = file_get_contents($inputPcm);
if ($mixed === false) {
    die("Erro: não consegui ler $inputPcm\n");
}

// instancia o canal opus (vamos trabalhar sempre internamente em 48k)
$opus = new opusChannel(48000, 1);

// helper: processar em blocos de 20ms @ 8k = 160 samples = 320 bytes
$frames = str_split($mixed, 320);

// buffers de saída
$pcm_original_8k          = $mixed;
$pcm_resample_48k_mono    = '';
$pcm_voice_48k_mono       = '';
$pcm_spatial_48k_stereo   = '';
$pcm_voice_8k_mono        = '';
$pcm_pipeline_full_48k    = '';

// loop por frame
foreach ($frames as $pcm8) {
    if ($pcm8 === '') {
        continue;
    }

    // 1) UP: 8k mono -> 48k mono (para trabalhar em melhor resolução)
    $pcm48_mono =  resampler($pcm8, $sampleInRate, 48000);

    // 2) VOICE CLARITY (em 48k mono)
    $pcm48_voice = $opus->enhanceVoiceClarity($pcm48_mono, 1.3);

    // 3) SPATIAL STEREO (em 48k, gera stereo)
    $pcm48_stereo = $opus->spatialStereoEnhance($pcm48_mono, 1.6, 0.7);

    // 4) PIPELINE COMPLETO: voice clarity + spatial estéreo
    $pcm48_voice_then_spatial = $opus->spatialStereoEnhance($pcm48_voice, 1.4, 0.6);

    // 5) VOICE CLARITY, mas retornando pra 8k (para comparar mesma taxa)
    $pcm_voice_8k = $opus->resample($pcm48_voice, 48000, $sampleInRate);

    // acumula
    $pcm_resample_48k_mono   .= $pcm48_mono;
    $pcm_voice_48k_mono      .= $pcm48_voice;
    $pcm_spatial_48k_stereo  .= $pcm48_stereo;
    $pcm_pipeline_full_48k   .= $pcm48_voice_then_spatial;
    $pcm_voice_8k_mono       .= $pcm_voice_8k;
}

// ------------------------------------------------------------
// GRAVA TODOS OS WAVE NA PASTA

// 1) Original 8k mono
file_put_contents(
    "$outDir/original_8k_mono.wav",
    pcmToWave($pcm_original_8k, $sampleInRate, $inChannels)
);

// 2) Apenas upsample 48k mono (sem enhancement)
file_put_contents(
    "$outDir/resample_48k_mono.wav",
    pcmToWave($pcm_resample_48k_mono, 48000, 1)
);

// 3) Voice clarity 48k mono
file_put_contents(
    "$outDir/voice_clarity_48k_mono.wav",
    pcmToWave($pcm_voice_48k_mono, 48000, 1)
);

// 4) Spatial stereo a partir de mono (48k stereo)
file_put_contents(
    "$outDir/spatial_48k_stereo.wav",
    pcmToWave($pcm_spatial_48k_stereo, 48000, 2)
);

// 5) Voice clarity mas mantendo 8k mono (pra comparar mesma taxa)
file_put_contents(
    "$outDir/voice_clarity_8k_mono.wav",
    pcmToWave($pcm_voice_8k_mono, $sampleInRate, 1)
);

// 6) Pipeline completo (voice clarity + spatial) em 48k estéreo
file_put_contents(
    "$outDir/pipeline_full_48k_stereo.wav",
    pcmToWave($pcm_pipeline_full_48k, 48000, 2)
);

echo "✅ Arquivos gerados em: $outDir\n";
