<?php

declare(strict_types=1);

function interleavePcmStereo(string $leftPcm, string $rightPcm): string|false {}

function monoToStereo(string $pcmData): string {}

function stereoToMono(string $pcmData): string {}

function psampler_binary_slice(object $source, int $offset = 0, ?int $length = null): string {}
