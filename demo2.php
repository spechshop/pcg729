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
// carrega o áudio PCM em 8kHz
$mixed = file_get_contents('mixed.pcm');
$calc = 320;
$pcms = str_split($mixed, $calc); // 20 ms @ 8kHz

// cria o resampler para 8000 → 48000
$kh = 16000;
$resampler = new Resampler(8000,$kh);







$wave = '';
$silence = str_repeat("\x00\x00", 160);

foreach ($pcms as $chunk) {
    $c = resampler($chunk, 8000, $kh);
    var_dump(strlen($c));
    $wave .= $c;

}


// grava WAV final resampleado
file_put_contents("output.wav", pcmToWave($wave, $kh, 1));

echo "✅ Gerado: output.wav (" . strlen($wave) . " bytes de áudio) chunks de $calc\n";
