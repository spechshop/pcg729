<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

$silenceFile = '/home/lotus/projetos/libspech/silence_5m.wav';
$musicFile   = '/home/lotus/projetos/libspech/music_mono_8000.wav';

$outDir = __DIR__;

echo "=== TESTE REAL interleavePcmStereo() com WAVs existentes ===\n\n";

if (!function_exists('interleavePcmStereo')) {
    fwrite(STDERR, "ERRO: interleavePcmStereo() nao existe. psampler nao carregou nesse binario.\n");
    exit(1);
}

function fail($msg)
{
    fwrite(STDERR, "[FAIL] {$msg}\n");
    exit(1);
}

function ok($msg)
{
    echo "[ OK ] {$msg}\n";
}

function read_wav_pcm($path)
{
    if (!file_exists($path)) {
        fail("Arquivo nao existe: {$path}");
    }

    $data = file_get_contents($path);

    if ($data === false || strlen($data) < 44) {
        fail("Nao consegui ler WAV valido: {$path}");
    }

    if (substr($data, 0, 4) !== 'RIFF' || substr($data, 8, 4) !== 'WAVE') {
        fail("Arquivo nao parece WAV RIFF: {$path}");
    }

    $pos = 12;
    $len = strlen($data);

    $fmt = null;
    $pcm = null;

    while ($pos + 8 <= $len) {
        $chunkId = substr($data, $pos, 4);
        $chunkSizeData = unpack('V', substr($data, $pos + 4, 4));
        $chunkSize = $chunkSizeData[1];

        $chunkDataStart = $pos + 8;
        $chunkDataEnd = $chunkDataStart + $chunkSize;

        if ($chunkDataEnd > $len) {
            fail("Chunk WAV quebrado em {$path}, chunk={$chunkId}");
        }

        if ($chunkId === 'fmt ') {
            if ($chunkSize < 16) {
                fail("Chunk fmt pequeno demais: {$path}");
            }

            $raw = substr($data, $chunkDataStart, 16);
            $fmt = unpack(
                'vaudioFormat/vchannels/VsampleRate/VbyteRate/vblockAlign/vbitsPerSample',
                $raw
            );
        } elseif ($chunkId === 'data') {
            $pcm = substr($data, $chunkDataStart, $chunkSize);
        }

        $pos = $chunkDataEnd;

        if (($chunkSize % 2) === 1) {
            $pos++;
        }
    }

    if ($fmt === null) {
        fail("Chunk fmt nao encontrado: {$path}");
    }

    if ($pcm === null) {
        fail("Chunk data nao encontrado: {$path}");
    }

    return array(
        'path' => $path,
        'audioFormat' => $fmt['audioFormat'],
        'channels' => $fmt['channels'],
        'sampleRate' => $fmt['sampleRate'],
        'byteRate' => $fmt['byteRate'],
        'blockAlign' => $fmt['blockAlign'],
        'bitsPerSample' => $fmt['bitsPerSample'],
        'pcm' => $pcm,
        'pcmBytes' => strlen($pcm),
        'samples' => (int)(strlen($pcm) / 2),
        'duration' => strlen($pcm) / 2 / $fmt['sampleRate'],
    );
}

function validate_mono_8k_16($wav, $name)
{
    if ((int)$wav['audioFormat'] !== 1) {
        fail("{$name}: audioFormat precisa ser PCM=1, veio {$wav['audioFormat']}");
    }

    if ((int)$wav['channels'] !== 1) {
        fail("{$name}: precisa ser mono, veio {$wav['channels']} canais");
    }

    if ((int)$wav['sampleRate'] !== 8000) {
        fail("{$name}: precisa ser 8000 Hz, veio {$wav['sampleRate']} Hz");
    }

    if ((int)$wav['bitsPerSample'] !== 16) {
        fail("{$name}: precisa ser 16-bit, veio {$wav['bitsPerSample']}");
    }

    if ((strlen($wav['pcm']) % 2) !== 0) {
        fail("{$name}: PCM com tamanho impar, desalinhado");
    }

    ok("{$name}: PCM mono 16-bit 8000 Hz validado");
}

function wav_header_pcm16($sampleRate, $channels, $dataBytes)
{
    $bitsPerSample = 16;
    $blockAlign = $channels * 2;
    $byteRate = $sampleRate * $blockAlign;
    $riffSize = 36 + $dataBytes;

    return
        'RIFF' .
        pack('V', $riffSize) .
        'WAVE' .
        'fmt ' .
        pack('V', 16) .
        pack('v', 1) .
        pack('v', $channels) .
        pack('V', $sampleRate) .
        pack('V', $byteRate) .
        pack('v', $blockAlign) .
        pack('v', $bitsPerSample) .
        'data' .
        pack('V', $dataBytes);
}

function write_wav_stereo_8k($path, $stereoPcm)
{
    $header = wav_header_pcm16(8000, 2, strlen($stereoPcm));
    file_put_contents($path, $header . $stereoPcm);
}

function sample_i16le_at($pcm, $index)
{
    $off = $index * 2;

    if ($off + 2 > strlen($pcm)) {
        return 0;
    }

    $u = unpack('v', substr($pcm, $off, 2));
    $v = $u[1];

    if ($v >= 0x8000) {
        $v -= 0x10000;
    }

    return $v;
}

function stereo_lr_at($stereo, $frame)
{
    $off = $frame * 4;

    if ($off + 4 > strlen($stereo)) {
        return array(0, 0);
    }

    $l = unpack('v', substr($stereo, $off, 2));
    $r = unpack('v', substr($stereo, $off + 2, 2));

    $lv = $l[1];
    $rv = $r[1];

    if ($lv >= 0x8000) {
        $lv -= 0x10000;
    }

    if ($rv >= 0x8000) {
        $rv -= 0x10000;
    }

    return array($lv, $rv);
}

function assert_same($label, $a, $b)
{
    if ($a !== $b) {
        fail("{$label}: esperado {$b}, veio {$a}");
    }

    ok($label);
}

function assert_true($label, $cond)
{
    if (!$cond) {
        fail($label);
    }

    ok($label);
}

$silence = read_wav_pcm($silenceFile);
$music = read_wav_pcm($musicFile);

validate_mono_8k_16($silence, 'silence_5m.wav');
validate_mono_8k_16($music, 'music_mono_8000.wav');

echo "\nInfo:\n";
echo "  silence bytes: {$silence['pcmBytes']} | samples: {$silence['samples']} | duracao: " . sprintf('%.2f', $silence['duration']) . "s\n";
echo "  music   bytes: {$music['pcmBytes']} | samples: {$music['samples']} | duracao: " . sprintf('%.2f', $music['duration']) . "s\n\n";

/*
 * Teste 1: silence L + music R, usando 10 segundos.
 */
$tenSecBytes = 8000 * 10 * 2;

$sil10 = substr($silence['pcm'], 0, $tenSecBytes);
$mus10 = substr($music['pcm'], 0, $tenSecBytes);

$stereo = interleavePcmStereo($sil10, $mus10);

if ($stereo === false) {
    fail("interleave 10s retornou false");
}

assert_same("10s stereo tamanho", strlen($stereo), 8000 * 10 * 4);

for ($i = 0; $i < 20; $i++) {
    list($l, $r) = stereo_lr_at($stereo, $i);

    $expectedL = sample_i16le_at($sil10, $i);
    $expectedR = sample_i16le_at($mus10, $i);

    if ($l !== $expectedL || $r !== $expectedR) {
        fail("10s L/R errado no frame {$i}: L={$l}/{$expectedL}, R={$r}/{$expectedR}");
    }
}

ok("10s silence L + music R conferido nos primeiros frames");

$out10 = $outDir . '/test_psampler_silence_L_music_R_10s.wav';
write_wav_stereo_8k($out10, $stereo);
ok("WAV stereo 10s gerado: {$out10}");

/*
 * Teste 2: music L + silence R, usando 10 segundos.
 */
$stereo2 = interleavePcmStereo($mus10, $sil10);

if ($stereo2 === false) {
    fail("interleave invertido 10s retornou false");
}

for ($i = 0; $i < 20; $i++) {
    list($l, $r) = stereo_lr_at($stereo2, $i);

    $expectedL = sample_i16le_at($mus10, $i);
    $expectedR = sample_i16le_at($sil10, $i);

    if ($l !== $expectedL || $r !== $expectedR) {
        fail("10s invertido L/R errado no frame {$i}: L={$l}/{$expectedL}, R={$r}/{$expectedR}");
    }
}

ok("10s music L + silence R conferido nos primeiros frames");

$out10inv = $outDir . '/test_psampler_music_L_silence_R_10s.wav';
write_wav_stereo_8k($out10inv, $stereo2);
ok("WAV stereo invertido 10s gerado: {$out10inv}");

/*
 * Teste 3: padding real.
 * music é menor que silence. Saída deve ter duração do maior.
 */
$full = interleavePcmStereo($silence['pcm'], $music['pcm']);

if ($full === false) {
    fail("interleave full retornou false");
}

$expectedFullBytes = max($silence['samples'], $music['samples']) * 4;
assert_same("full stereo tamanho com padding", strlen($full), $expectedFullBytes);

$musicLastFrame = $music['samples'] - 1;
$afterMusicFrame = $music['samples'] + 100;

list($l1, $r1) = stereo_lr_at($full, $musicLastFrame);
assert_same("full ultimo frame de musica R preservado", $r1, sample_i16le_at($music['pcm'], $musicLastFrame));

list($l2, $r2) = stereo_lr_at($full, $afterMusicFrame);
assert_same("full depois que music acaba R vira silencio", $r2, 0);

$outFull = $outDir . '/test_psampler_silence_L_music_R_full_padding.wav';
write_wav_stereo_8k($outFull, $full);
ok("WAV stereo full com padding gerado: {$outFull}");

/*
 * Teste 4: PCM quebrado.
 */
$bad = @interleavePcmStereo("\x01", $mus10);

assert_true("PCM impar retorna false", $bad === false);

/*
 * Teste 5: chunks de 20ms, igual painel.
 */
$chunkBytes = 160 * 2; // 20ms @ 8kHz mono 16-bit
$frames = 0;
$totalOut = 0;
$offset = 0;
$maxInput = min(strlen($silence['pcm']), strlen($music['pcm']));

$start = microtime(true);

while ($offset < $maxInput) {
    $lchunk = substr($silence['pcm'], $offset, $chunkBytes);
    $rchunk = substr($music['pcm'], $offset, $chunkBytes);

    if ($lchunk === '' && $rchunk === '') {
        break;
    }

    $payload = interleavePcmStereo($lchunk, $rchunk);

    if ($payload === false) {
        fail("chunk 20ms retornou false no offset {$offset}");
    }

    $expected = max((int)(strlen($lchunk) / 2), (int)(strlen($rchunk) / 2)) * 4;

    if (strlen($payload) !== $expected) {
        fail("chunk 20ms tamanho errado no offset {$offset}: veio " . strlen($payload) . ", esperado {$expected}");
    }

    $totalOut += strlen($payload);
    $frames++;
    $offset += $chunkBytes;
}

$elapsed = microtime(true) - $start;
$audioSeconds = ($frames * 0.020);
$factor = $elapsed > 0 ? ($audioSeconds / $elapsed) : 0;

ok("chunks 20ms processados sem erro");

echo "\nBenchmark chunk 20ms:\n";
echo "  chunks: {$frames}\n";
echo "  audio simulado: " . sprintf('%.2f', $audioSeconds) . "s\n";
echo "  bytes stereo gerados: {$totalOut}\n";
echo "  tempo gasto: " . sprintf('%.6f', $elapsed) . "s\n";
echo "  fator tempo real: " . sprintf('%.2f', $factor) . "x\n";

/*
 * Teste 6: salvar RAW curto, sem tocar.
 */
$raw10 = $outDir . '/test_psampler_silence_L_music_R_10s.s16le';
file_put_contents($raw10, $stereo);
ok("RAW stereo 10s gerado: {$raw10}");

echo "\nArquivos gerados, sem reproduzir nada porque ainda existe civilizacao:\n";
echo "  {$out10}\n";
echo "  {$out10inv}\n";
echo "  {$outFull}\n";
echo "  {$raw10}\n";

echo "\nComando para inspecionar sem tocar:\n";
echo "  file {$out10}\n";
echo "  ls -lh test_psampler_*.wav test_psampler_*.s16le\n";

echo "\nSe algum dia quiser ouvir baixo, sem acordar a ONU residencial:\n";
echo "  ffplay -nodisp -autoexit -volume 10 {$out10}\n";

echo "\nRESULTADO: PASSOU. interleavePcmStereo parece comportado com WAV real.\n";
exit(0);