#!/usr/bin/env python3
"""Compile only recvfromInto's translation unit and the fixture, then relink.

The baseline is extracted from the task's starting commit, never from installed PHP.
All other objects, flags and the fixture are identical between before/after.
"""
import importlib.util
import hashlib
import json
import os
from pathlib import Path
import subprocess

OUT = Path(__file__).resolve().parent
ROOT = OUT.parents[2]
SRC = ROOT / 'source/php-src'
os.environ['PATH'] = '/usr/local/musl/bin:' + os.environ['PATH']
spec = importlib.util.spec_from_file_location('fixtures', OUT.parent / 'build_fixtures.py')
fixtures = importlib.util.module_from_spec(spec)
spec.loader.exec_module(fixtures)

(OUT / 'modules').mkdir(exist_ok=True)
(OUT / 'baseline').mkdir(exist_ok=True)
(OUT / 'results').mkdir(exist_ok=True)
original = (SRC / 'main/internal_functions_cli.c').read_text()
modified = original.replace('static zend_module_entry * const php_builtin_extensions[] = {',
    'extern zend_module_entry swoole_buffer_test_module_entry;\n'
    'static zend_module_entry * const php_builtin_extensions[] = {\n &swoole_buffer_test_module_entry,')
(OUT / 'modules/internal.c').write_text(modified)
fixtures.compile(OUT / 'modules/internal.c', OUT / 'modules/internal.o')
fixtures.compile(ROOT / 'tests/native_swoole_buffer/swoole_buffer_test.c', OUT / 'modules/fixture.o')
source = 'ext/swoole/ext-src/swoole_socket_coro.cc'
obj = 'ext/swoole/ext-src/swoole_socket_coro.lo'
makefile = (SRC / 'Makefile').read_text()
recipe = next(line for line in makefile.splitlines() if line.startswith('\t') and f'-o {obj} ' in line)
# Baseline pinned to the starting repository commit; full patch is applied to a
# pristine upstream file by the reproducibility harness before this is needed.
baseline = OUT / 'baseline/swoole_socket_coro.cc'
if not baseline.exists():
    raise SystemExit('Run check_patches.py to prepare the pinned baseline first')
manifest = json.loads((ROOT / 'src/globals/patch/swoole_native_buffer_v630rc1.json').read_text())
assert hashlib.sha256(baseline.read_bytes()).hexdigest() in manifest['lifecycle_upgrade']['files']['ext-src/swoole_socket_coro.cc']
assert hashlib.sha256((SRC / source).read_bytes()).hexdigest() in manifest['files']['ext-src/swoole_socket_coro.cc']['after']
for name, cc in [('before', baseline), ('after', SRC / source)]:
    target = OUT / f'modules/{name}.lo'
    command = recipe.replace(str(SRC / source), str(cc)).replace(obj, str(target))
    command = command.replace('ext/swoole/ext-src/swoole_socket_coro.dep', str(OUT / f'modules/{name}.dep'))
    mk = OUT / 'modules/build.mk'
    mk.write_text('lifecycle-compile:\n' + command + '\n'
        'PHP_GLOBAL_OBJS := $(filter-out ext/swoole/ext-src/swoole_socket_coro.lo,$(PHP_GLOBAL_OBJS)) '
        + str(target) + '\n'
        'lifecycle-link:\n\t$(BUILD_CLI)\n')
    with (OUT / f'results/build-{name}.txt').open('w') as log:
        for task in ['lifecycle-compile', 'lifecycle-link']:
            subprocess.run(['make', '--no-print-directory', '-s', '-f', 'Makefile', '-f', str(mk), task,
                'EXTRA_CXXFLAGS=-g',
                'PHP_BINARY_OBJS=' + str(OUT / 'modules/internal.o') + ' ' + str(OUT / 'modules/fixture.o'),
                'SAPI_CLI_PATH=' + str(OUT / f'php-{name}')], cwd=SRC, stdout=log, stderr=subprocess.STDOUT, check=True)
    print(name, 'compiled and relinked', flush=True)

# Reuse the existing optional contract fixture; no adapter code is introduced.
fixtures.compile(ROOT / 'tests/native_swoole_buffer/swoole_buffer_test.c',
    OUT / 'modules/contract.o', ['-DSWOOLE_BUFFER_TEST_PSAMPLER=1'])
mk = OUT / 'modules/contract.mk'
mk.write_text('PHP_GLOBAL_OBJS := $(filter-out ext/swoole/ext-src/swoole_socket_coro.lo,$(PHP_GLOBAL_OBJS)) '
    + str(OUT / 'modules/after.lo') + '\nlifecycle-contract:\n\t$(BUILD_CLI)\n')
with (OUT / 'results/build-contract.txt').open('w') as log:
    subprocess.run(['make', '--no-print-directory', '-s', '-f', 'Makefile', '-f', str(mk), 'lifecycle-contract',
        'PHP_BINARY_OBJS=' + str(OUT / 'modules/internal.o') + ' ' + str(OUT / 'modules/contract.o'),
        'SAPI_CLI_PATH=' + str(OUT / 'php-contract')], cwd=SRC, stdout=log, stderr=subprocess.STDOUT, check=True)
print('contract relinked', flush=True)
