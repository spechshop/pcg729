from build_fixtures import ROOT, OUT, SRC, compile, original
import subprocess
s=original.replace('\tphpext_swoole_ptr,','')
(OUT/'modules/internal_no_swoole.c').write_text(s)
compile(OUT/'modules/internal_no_swoole.c',OUT/'modules/internal_no_swoole.o')
with (OUT/'results/build-no-swoole.txt').open('w') as log:
 subprocess.run(['make','-s','-f','Makefile','-f',str(OUT/'modules/link.mk'),'validation-link',f'PHP_BINARY_OBJS={OUT}/modules/internal_no_swoole.o',f'SAPI_CLI_PATH={OUT}/php-no-swoole'],cwd=SRC,stdout=log,stderr=subprocess.STDOUT,check=True)
