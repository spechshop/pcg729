#!/usr/bin/env bash
set -euo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")"
export PATH="/usr/local/musl/bin:/usr/local/musl/x86_64-linux-musl/bin:$PATH"
export LIBRARY_PATH=/usr/local/musl/x86_64-linux-musl/lib
export LD_LIBRARY_PATH=/usr/local/musl/x86_64-linux-musl/lib
make -C php-src -j2 sapi/cli/php
cp php-src/sapi/cli/php bin/php
