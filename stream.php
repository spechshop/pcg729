<?php
// Initialize opus channel for 48kHz mono audio
$opus = new opusChannel(48000, 1);

// Read input PCM file
$file = 'mixed.pcm';
$pcmData = file_get_contents($file);

// Split into 20ms chunks (320 bytes @ 8kHz)
$chunkSize = 320;
$chunks = str_split($pcmData, $chunkSize);

// Process chunks
$outputBuffer = '';
$inputFreq = 8000;

foreach ($chunks as $chunk) {
    // Resample from 8kHz to target frequency
    $resampledChunk = $opus->resample($chunk, 8000, $inputFreq);
    $outputBuffer .= $resampledChunk;
}


var_dump(strlen($outputBuffer));