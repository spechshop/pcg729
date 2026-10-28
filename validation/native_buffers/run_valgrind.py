#!/usr/bin/env python3
from pathlib import Path
import subprocess,re,tempfile,json,os
OUT=Path(__file__).resolve().parent;ROOT=OUT.parents[1]
VG='/tmp/pcg729-valgrind/root/usr/bin/valgrind'
env=dict(os.environ,VALGRIND_LIB='/tmp/pcg729-valgrind/root/usr/libexec/valgrind',USE_ZEND_ALLOC='0')
rows=[]
def run(name,path,exitcode=0,binary='php-fixtures'):
 log=OUT/'results'/('memcheck-'+name+'.txt')
 cmd=[VG,'--soname-synonyms=somalloc=*','--tool=memcheck','--leak-check=full','--errors-for-leak-kinds=definite,indirect','--error-exitcode=97',f'--log-file={log}',str(OUT/binary),'-n','-d','log_errors=0','-d','display_errors=1','-d','error_reporting=24575',str(path)]
 p=subprocess.run(cmd,cwd=ROOT,env=env,capture_output=True,text=True,timeout=180)
 t=log.read_text();errors=re.search(r'ERROR SUMMARY: ([\d,]+) errors',t);alloc=re.search(r'total heap usage: ([\d,]+) allocs, ([\d,]+) frees, ([\d,]+) bytes',t)
 record=dict(case=name,command=cmd,exit=p.returncode,expected_exit=exitcode,signal=-p.returncode if p.returncode<0 else None,errors=int(errors[1].replace(',',''))if errors else None,heap_allocs=int(alloc[1].replace(',',''))if alloc else None,stdout=p.stdout,stderr=p.stderr)
 record['instrumentation_ok']=record['heap_allocs'] is not None and record['heap_allocs']>10000
 record['memcheck_pass']=record['errors']==0 and record['exit']==exitcode and record['instrumentation_ok']
 rows.append(record);(OUT/'results/memcheck-summary.json').write_text(json.dumps(rows,indent=2)+'\n')
 print(name,'exit',p.returncode,'errors',record['errors'],'allocs',record['heap_allocs'],flush=True)
for path in sorted((ROOT/'tests/native_swoole_buffer').glob('*.phpt')):
 code=re.search(r'--FILE--\n(.*?)\n--EXPECT',path.read_text(),re.S).group(1)
 with tempfile.NamedTemporaryFile(mode='w',dir=path.parent,suffix='.php',prefix='.memcheck-',delete=False)as f:f.write(code);tmp=Path(f.name)
 try:run('swoole-'+path.stem,tmp,255 if path.stem in ['bailout','coroutine_create','coroutine_suspend']else 0,'php-contract' if path.stem=='psampler_contract'else'php-fixtures')
 finally:tmp.unlink()
for path in sorted(Path('/home/lotus/projetos/psampler/tests').glob('*.phpt')):
 if path.stem.startswith('binary_provider_')and'future'not in path.stem or path.stem in ['byte_buffer_native','byte_buffer_native_ring','byte_buffer_native_errors','byte_buffer_native_lifecycle']:
  code=re.search(r'--FILE--\n(.*?)\n--EXPECT',path.read_text(),re.S).group(1)
  with tempfile.NamedTemporaryFile(mode='w',dir=path.parent,suffix='.php',prefix='.memcheck-',delete=False)as f:f.write(code);tmp=Path(f.name)
  try:run('psampler-'+path.stem,tmp)
  finally:tmp.unlink()
for name in ['functional','binary_bailout','pcm_api','suspension','peer_exception','peer_bailout','pcm_bailout','zts']:
 path=OUT/(name+('.inc' if name in ['functional','binary_bailout','pcm_api','suspension']else'.php'))
 run(name,path,255 if 'bailout'in name else 0)
