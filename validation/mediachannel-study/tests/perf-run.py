from pathlib import Path
import subprocess,os,json
r=Path(__file__).resolve().parents[1];php=str(r.parents[1]/'buildroot/bin/php');cpu=min(os.sched_getaffinity(0));rows=[]
for rep in range(3):
 for scenario in ['one','same','transcode','dtmf','acoustic']:
  for v in (['baseline','optimization'] if rep%2==0 else ['optimization','baseline']):
   log=r/'evidence/perf'/f'{scenario}-{v}-{rep}.csv'
   cmd=['/usr/bin/perf','stat','-x',';','-e','task-clock,context-switches,cycles,instructions','-o',str(log),'taskset','-c',str(cpu),php,str(r/'tests/bench.php'),str(r/'lab'/v),scenario,'30']
   p=subprocess.run(cmd,capture_output=True,text=True,timeout=15);rows.append(dict(command=cmd,exit=p.returncode,stdout=p.stdout,stderr=p.stderr,cpu=cpu))
(r/'evidence/resultados/perf-runs.json').write_text(json.dumps(rows,indent=2))
