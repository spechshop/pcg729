#!/usr/bin/env python3
from pathlib import Path
import subprocess,re,tempfile,json,os
OUT=Path(__file__).resolve().parent;ROOT=OUT.parents[1]
VG='/tmp/pcg729-valgrind/root/usr/bin/valgrind'
env=dict(os.environ,VALGRIND_LIB='/tmp/pcg729-valgrind/root/usr/libexec/valgrind',USE_ZEND_ALLOC='0',USE_TRACKED_ALLOC='1')
rows=[]
def run(name,path,expected,extra=()):
 log=OUT/'results'/('memcheck-tracked-'+name+'.txt')
 cmd=[VG,'--soname-synonyms=somalloc=*','--tool=memcheck','--leak-check=full','--errors-for-leak-kinds=definite,indirect','--error-exitcode=97',f'--log-file={log}',str(OUT/'php-fixtures'),'-n','-d','log_errors=0','-d','display_errors=1','-d','error_reporting=24575',str(path),*map(str,extra)]
 p=subprocess.run(cmd,cwd=ROOT,env=env,capture_output=True,text=True,timeout=180)
 t=log.read_text();n=lambda pattern:int(re.search(pattern,t)[1].replace(',',''))if re.search(pattern,t)else None
 r=dict(case=name,command=cmd,exit=p.returncode,expected_exit=expected,signal=-p.returncode if p.returncode<0 else None,errors=n(r'ERROR SUMMARY: ([\d,]+) errors'),allocs=n(r'total heap usage: ([\d,]+) allocs'),frees=n(r'allocs, ([\d,]+) frees'),allocated_bytes=n(r'frees, ([\d,]+) bytes allocated'),definitely_lost=n(r'definitely lost: ([\d,]+) bytes'),indirectly_lost=n(r'indirectly lost: ([\d,]+) bytes'),stdout=p.stdout,stderr=p.stderr)
 rows.append(r);(OUT/'results/memcheck-tracked-summary.json').write_text(json.dumps(rows,indent=2)+'\n');print(name,'exit',p.returncode,'errors',r['errors'],'allocs',r['allocs'],flush=True)
if __name__ == '__main__':
    for name in ['bailout','coroutine_create','coroutine_suspend']:
     path=ROOT/'tests/native_swoole_buffer'/(name+'.phpt');code=re.search(r'--FILE--\n(.*?)\n--EXPECT',path.read_text(),re.S).group(1)
     with tempfile.NamedTemporaryFile(mode='w',dir=path.parent,suffix='.php',prefix='.memcheck-',delete=False)as f:f.write(code);tmp=Path(f.name)
     try:run('swoole-'+name,tmp,255)
     finally:tmp.unlink()
    for name in ['fatal_control','binary_bailout','peer_bailout','pcm_bailout']:
     run(name,OUT/(name+('.inc'if name=='binary_bailout'else'.php')),255)
    for method in ['recvfrom','recvfromInto']:
     run('alloc-'+method,OUT/'udp_bench.php',0,[method,172,10000,32,0,4,0])
    run("zts",OUT/"zts.php",0)
    run("fatal_coroutine_control",OUT/"fatal_coroutine_control.php",255)
    run("zts_control",OUT/"zts_control.php",0)
