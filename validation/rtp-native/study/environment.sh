#!/bin/bash
set -eu
uname -a
lscpu
buildroot/bin/php -v
buildroot/bin/php --ri psampler
buildroot/bin/php --ri swoole
buildroot/bin/php -r 'echo new ReflectionFunction("psampler_rtp_parse"); echo new ReflectionMethod("Swoole\Coroutine\Socket", "recvfromInto");'
file buildroot/bin/php
sha256sum buildroot/bin/php validation/rtp-native/study/php-baseline source/php-src/ext/psampler/binary_buffer.c downloads/psampler/binary_buffer.c
sha256sum -c validation/rtp-native/study/originals.sha256
nm buildroot/bin/php | rg 'zif_psampler_rtp_parse|rtp_parse_view|rtp_property_names'
