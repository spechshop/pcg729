from pathlib import Path
import os,subprocess
out=Path(__file__).resolve().parent
src=out/'php-src'
fixture=src/'validation-fixture'
fixture.mkdir(exist_ok=True)
s=(src/'main/internal_functions_cli.c').read_text()
s=s.replace('static zend_module_entry * const php_builtin_extensions[] = {','extern zend_module_entry psampler_binary_test_module_entry;\nstatic zend_module_entry * const php_builtin_extensions[] = {\n    &psampler_binary_test_module_entry,')
(fixture/'main.c').write_text(s)
env=os.environ.copy();env['PATH']='/usr/local/musl/bin:/usr/local/musl/x86_64-linux-musl/bin:'+env['PATH']
env['LIBRARY_PATH']=env['LD_LIBRARY_PATH']='/usr/local/musl/x86_64-linux-musl/lib'
lines=(out/'build-musl.log').read_text().splitlines()
cmd=next(x for x in lines if ' -c ' in x and '/pcm_core.c -o ' in x)
for source,target in [(src/'ext/psampler/tests/native_binary_provider/psampler_binary_test.c','provider'),(fixture/'main.c','main')]:
    compile=cmd.replace(str(src/'ext/psampler/pcm_core.c'),str(source)).replace('ext/psampler/pcm_core.lo',f'validation-fixture/{target}.lo').replace('ext/psampler/pcm_core.dep',f'validation-fixture/{target}.dep')
    subprocess.run(compile,cwd=src,env=env,shell=True,check=True)
(fixture/'Makefile').write_text('''include Makefile
PHP_GLOBAL_OBJS += validation-fixture/provider.lo
PHP_BINARY_OBJS = validation-fixture/main.lo
SAPI_CLI_PATH = '''+str(out/'bin/php-fixture')+'''
.PHONY: fixture-link
fixture-link:
\t$(BUILD_CLI)
''')
subprocess.run(['make','-f','validation-fixture/Makefile','fixture-link'],cwd=src,env=env,check=True)
