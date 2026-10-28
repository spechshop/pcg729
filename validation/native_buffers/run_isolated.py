#!/usr/bin/env python3
from pathlib import Path
import re,subprocess,tempfile,json
OUT=Path(__file__).resolve().parent;ROOT=OUT.parents[1];PHP=OUT/'php-fixtures'
records=[]
def execute(name,script,expected,cleanup=None):
 cmd=[str(PHP),'-n','-d','display_errors=1','-d','log_errors=0','-d','error_reporting=24575',str(script)]
 p=subprocess.run(cmd,cwd=ROOT,capture_output=True,text=True,timeout=30)
 record={'case':name,'command':cmd,'exit':p.returncode,'expected_exit':expected,'signal':-p.returncode if p.returncode<0 else None,'cleanup_marker':cleanup,'cleanup_present':cleanup in p.stdout if cleanup else None,'exit_ok':p.returncode==expected,'stdout':p.stdout,'stderr':p.stderr}
 record['status']='PASS'if record['exit_ok'] and (cleanup is None or record['cleanup_present']) else 'FAIL'
 records.append(record);print(name,record['status'],'exit',p.returncode,flush=True)
for phpt in sorted((ROOT/'tests/native_swoole_buffer').glob('*.phpt')):
 if phpt.stem=='psampler_contract':continue
 text=phpt.read_text();code=re.search(r'--FILE--\n(.*?)\n--EXPECT',text,re.S).group(1)
 with tempfile.NamedTemporaryFile(mode='w',suffix='.php',prefix='.isolated-',dir=phpt.parent,delete=False) as f:f.write(code);tmp=Path(f.name)
 try:execute(phpt.name,tmp,255 if phpt.stem in ['bailout','coroutine_create','coroutine_suspend'] else 0)
 finally:tmp.unlink()
for name,exitcode,cleanup in [
 ('functional',0,'"status":"PASS"'),('peer_exception',0,'peer exception cleanup PASS'),
 ('peer_bailout',255,'peer bailout guard released'),('pcm_bailout',255,'PCM bailout cleanup PASS'),
 ('binary_bailout',255,'binary bailout guard/release PASS'),('suspension',0,'scheduler recovery PASS'),
 ('pcm_api',0,'10000 cycles PASS'),('zts',0,'40000 independent object cycles PASS'),
 ('zts_transfer',0,'request-local Buffer and ByteBuffer rejected PASS')]:
 execute(name,OUT/(name+('.inc' if name in ['functional','pcm_api','binary_bailout','suspension'] else '.php')),exitcode,cleanup)
(OUT/'results/isolated.json').write_text(json.dumps(records,indent=2)+'\n')
print('passes',sum(x['status']=='PASS'for x in records),'fails',sum(x['status']=='FAIL'for x in records))
