from pathlib import Path
import shutil, os, hashlib, json, subprocess, sys
root=Path('/home/lotus/CLionProjects/pcg729')
out=root/'validation/psampler-0.7.0'
src=root/'source/php-src'; dst=out/'php-src'
shutil.copytree(src,dst,symlinks=True)
count=0
for p in dst.rglob('*'):
    if p.is_symlink() or not p.is_file(): continue
    if p.name in ['Makefile','Makefile.objects','Makefile.fragments','config.status','config.nice','libtool','php-config','phpize'] or p.suffix in ['.dep','.la','.lo']:
        data=p.read_bytes(); new=data.replace(str(src).encode(),str(dst).encode())
        if data!=new:
            stat=p.stat();p.write_bytes(new);os.utime(p,ns=(stat.st_atime_ns,stat.st_mtime_ns));count+=1
local=Path('/home/lotus/projetos/psampler')
ext=dst/'ext/psampler'
# Only extension sources and test assets: no downloads, no deletion of existing builds.
for p in local.iterdir():
    if p.is_file() and (p.suffix in ['.c','.h','.inc','.m4','.php'] or p.name=='LICENSE'):
        shutil.copy2(p,ext/p.name)
shutil.copytree(local/'tests',ext/'tests',dirs_exist_ok=True)
for p in ext.iterdir():
    if p.suffix in ['.c','.h','.inc']: os.utime(p,None)
(out/'original-modules.json').write_text(subprocess.check_output(['/usr/local/bin/php','-r','echo json_encode(get_loaded_extensions());']).decode())
(out/'original-php.sha256').write_text(hashlib.sha256(Path('/usr/local/bin/php').read_bytes()).hexdigest()+'\n')
subprocess.run([sys.executable, str(out/'configure-ignores.py')], check=True)
print('Isolated build:',dst,'relocated controls:',count)
