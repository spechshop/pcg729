#!/usr/bin/env bash
set -eu
PS=/home/lotus/projetos/psampler
OUT=$(cd -- "$(dirname -- "$0")" && pwd)
for test in pcm_stream_asan pcm_analyzer_asan pcm_cache_threads; do
  extra=()
  if [[ "$test" == pcm_analyzer_asan ]]; then extra+=("$PS/pcm_analyzer.c"); fi
  gcc -g -O1 -fsanitize=address,undefined -fno-omit-frame-pointer -DPSAMPLER_STANDALONE -DPSAMPLER_DSP_THREADS -pthread "$PS/tests/$test.c" "$PS/pcm_standalone.c" "$PS/pcm_core.c" "${extra[@]}" -lm -lcrypto -o "$OUT/modules/$test"
  ASAN_OPTIONS=detect_leaks=1:abort_on_error=1 UBSAN_OPTIONS=halt_on_error=1 "$OUT/modules/$test" > "$OUT/results/$test.txt" 2>&1
  echo "$test exit=0"
done
