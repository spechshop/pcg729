<?php
declare(strict_types=1);

namespace libspech\audio;

/** Confirmation of acoustic voice activity; never a mailbox classification. */
final class EarlyMediaVoicePolicy
{
    private int $voiceMs = 0;
    private int $pauseMs = 0;
    private bool $decided = false;

    public function frame(string $frameSignal, string $stableSignal): bool
    {
        if ($this->decided) return false;
        if ($frameSignal === 'voice_like' && $stableSignal === 'voice_like') {
            $this->voiceMs += 20;
            $this->pauseMs = 0;
        } else {
            // Native transient classifications within a syllable are gaps too.
            // Silence/tones/noise/music never count, even during speech hangover.
            $this->pauseMs += 20;
            if ($this->pauseMs > 200) $this->voiceMs = 0;
        }
        if ($this->voiceMs < 1000) return false;
        return $this->decided = true;
    }

    public function resetEvidence(): void
    {
        $this->voiceMs = $this->pauseMs = 0;
    }

    public function voiceMs(): int { return $this->voiceMs; }
}
