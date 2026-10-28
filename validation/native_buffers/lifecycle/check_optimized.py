#!/usr/bin/env python3
"""Check longjmp state at -O2 without rebuilding PHP or replacing any object."""
from pathlib import Path
import os
import subprocess

OUT = Path(__file__).resolve().parent
ROOT = OUT.parents[2]
SRC = ROOT / 'source/php-src'
os.environ['PATH'] = '/usr/local/musl/bin:' + os.environ['PATH']
old = 'ext/swoole/ext-src/swoole_socket_coro.lo'
recipe = next(s for s in (SRC / 'Makefile').read_text().splitlines()
    if s.startswith('\t') and '-o ' + old + ' ' in s)
recipe = recipe.replace(old, str(OUT / 'modules/optimized.lo')).replace(
    'ext/swoole/ext-src/swoole_socket_coro.dep', str(OUT / 'modules/optimized.dep'))
mk = OUT / 'modules/optimized.mk'
mk.write_text('lifecycle-optimized-compile:\n' + recipe + '\n'
    'PHP_GLOBAL_OBJS := $(filter-out ' + old + ',$(PHP_GLOBAL_OBJS)) '
    + str(OUT / 'modules/optimized.lo') + '\nlifecycle-optimized-link:\n\t$(BUILD_CLI)\n')
with (OUT / 'results/build-optimized.txt').open('w') as log:
    for task in ['lifecycle-optimized-compile', 'lifecycle-optimized-link']:
        subprocess.run(['make', '--no-print-directory', '-s', '-f', 'Makefile', '-f', str(mk), task,
            'EXTRA_CXXFLAGS=-O2 -g -Wextra -Wclobbered',
            'PHP_BINARY_OBJS=' + str(OUT / 'modules/internal.o') + ' ' + str(OUT / 'modules/contract.o'),
            'SAPI_CLI_PATH=' + str(OUT / 'php-optimized')], cwd=SRC, stdout=log, stderr=subprocess.STDOUT, check=True)
env = dict(os.environ, TEST_PHP_EXECUTABLE=str(OUT / 'php-optimized'), REPORT_EXIT_STATUS='1')
with (OUT / 'results/phpt-optimized.txt').open('w') as log:
    subprocess.run([str(OUT / 'php-optimized'), '-n', str(SRC / 'run-tests.php'), '-n', '-q',
        str(ROOT / 'tests/native_swoole_buffer')], env=env, cwd=ROOT, stdout=log, stderr=subprocess.STDOUT, check=True)
print('optimized receive object: all 14 PHPTs PASS')
