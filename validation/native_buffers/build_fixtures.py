#!/usr/bin/env python3
"""Relink existing PHP objects with test-only modules. Never rebuild/replace PHP."""
from pathlib import Path
import subprocess, hashlib, json, os
os.environ['PATH'] = '/usr/local/musl/bin:' + os.environ['PATH']
ROOT = Path(__file__).resolve().parents[2]
OUT = Path(__file__).resolve().parent
SRC = ROOT / 'source/php-src'
PS = Path('/home/lotus/projetos/psampler')
flags = ['x86_64-linux-musl-gcc','-c','-g','-O2','-Wall','-Wextra','-Wno-unused-parameter', '-pthread']
flags += [f'-I{ROOT / "buildroot/include"}', f'-I{ROOT / "buildroot/include/libxml2"}']
flags += [f'-I{SRC / p}' for p in ['', 'main','Zend','TSRM','ext','ext/date/lib']]
def compile(source, output, extra=()):
    subprocess.run(flags + list(extra) + [str(source),'-o',str(output)],check=True)
original = (SRC/'main/internal_functions_cli.c').read_text()
modules = 'extern zend_module_entry swoole_buffer_test_module_entry;\nextern zend_module_entry psampler_binary_test_module_entry;\nextern zend_module_entry pcm_api_test_module_entry;\n'
modified = original.replace('static zend_module_entry * const php_builtin_extensions[] = {', modules + '\nstatic zend_module_entry * const php_builtin_extensions[] = {\n &swoole_buffer_test_module_entry,\n &psampler_binary_test_module_entry,\n &pcm_api_test_module_entry,')
if __name__ == '__main__':
    (OUT/'modules/internal_functions_cli.c').write_text(modified)
    compile(OUT/'modules/internal_functions_cli.c', OUT/'modules/internal_functions_cli.o')
    compile(PS/'tests/native_binary_provider/psampler_binary_test.c',OUT/'modules/psampler_binary_test.o')
    compile(OUT/'pcm_api_test.c',OUT/'modules/pcm_api_test.o')
    (OUT/'modules/link.mk').write_text('validation-link:\n\t$(BUILD_CLI)\n')
    for name, defines in [('php-fixtures', []),('php-contract',['-DSWOOLE_BUFFER_TEST_PSAMPLER=1'])]:
        compile(ROOT/'tests/native_swoole_buffer/swoole_buffer_test.c',OUT/f'modules/{name}.o',defines)
        objects = [OUT/'modules/pcm_api_test.o',OUT/'modules/internal_functions_cli.o',OUT/'modules/psampler_binary_test.o',OUT/f'modules/{name}.o']
        with (OUT/f'results/build-{name}.txt').open('w') as log:
            subprocess.run(['make','--no-print-directory','-s','-f','Makefile','-f',str(OUT/'modules/link.mk'),'validation-link', 'PHP_BINARY_OBJS='+' '.join(map(str,objects)),f'SAPI_CLI_PATH={OUT/name}'],cwd=SRC,stdout=log,stderr=subprocess.STDOUT,check=True)
        print(name, 'ready', flush=True)
    print('original sha256',hashlib.sha256((ROOT/'buildroot/bin/php').read_bytes()).hexdigest())
