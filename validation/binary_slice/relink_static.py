#!/usr/bin/env python3
"""Relink the configured PHP CLI objects statically without changing global PHP.

Run make clean && make first with /usr/local/musl/bin on PATH. The local
Makefile's libtool invocation can otherwise silently produce a dynamic binary.
Only sapi/cli/php is written; install it into buildroot/bin/php after success.
"""
import os
from pathlib import Path
import shlex
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]
SRC = ROOT / 'source/php-src'
env = dict(os.environ, PATH='/usr/local/musl/bin:' + os.environ['PATH'])
with tempfile.NamedTemporaryFile(mode='w', suffix='.mk') as recipe:
    recipe.write('EXTRA_LDFLAGS_PROGRAM += -all-static\nrelease-link:\n\t$(BUILD_CLI)\n')
    recipe.flush()
    line = subprocess.check_output(['make', '--no-print-directory', '-n', '-f',
        'Makefile', '-f', recipe.name, 'release-link'], cwd=SRC, env=env, text=True).splitlines()[0]
args = shlex.split(line)
args = args[args.index('x86_64-linux-musl-gcc'):]
# GCC needs the libtool options translated and the libtool rpath pairs removed.
args = [x for i, x in enumerate(args) if x != '-R' and (i == 0 or args[i-1] != '-R')]
args = ['-static' if x == '-all-static' else '-Wl,-export-dynamic' if x == '-export-dynamic' else x for x in args]
subprocess.run(args, cwd=SRC, env=env, check=True)
subprocess.run(['file', str(SRC / 'sapi/cli/php')], check=True)
