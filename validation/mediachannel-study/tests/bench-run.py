from pathlib import Path
import subprocess,json,random,statistics
r=Path(__file__).resolve().parents[1];php=str(r.parents[1]/'buildroot/bin/php'); rows=[]
f=(r/'evidence/resultados/bench.jsonl').open('w')
for repeat in range(5):
 for scenario in ['micro','one','same','transcode','dtmf','acoustic']:
  variants=['baseline','optimization'] if repeat%2==0 else ['optimization','baseline']
  for v in variants:
   p=subprocess.run([php,str(r/'tests/bench.php'),str(r/'lab'/v),scenario,'10000' if scenario=='micro' else '50'],capture_output=True,text=True,timeout=15)
   row=dict(variant=v,repeat=repeat,exit=p.returncode,stderr=p.stderr)
   try:row.update(json.loads(p.stdout.strip().splitlines()[-1]))
   except Exception:row['stdout']=p.stdout
   rows.append(row);f.write(json.dumps(row)+'\n');f.flush()
summary=[]
for scenario in ['micro','one','same','transcode','dtmf','acoustic']:
 d={'scenario':scenario}
 for v in ['baseline','optimization']:
  a=[x['cpu'] for x in rows if x['variant']==v and x['scenario']==scenario and x['exit']==0]
  d[v]={'median_cpu_s':statistics.median(a),'min':min(a),'max':max(a),'stdev':statistics.stdev(a)} if a else None
 if d['baseline'] and d['optimization']:d['median_gain_pct']=100*(1-d['optimization']['median_cpu_s']/d['baseline']['median_cpu_s'])
 summary.append(d)
(r/'evidence/resultados/bench-summary.json').write_text(json.dumps(summary,indent=2));print(json.dumps(summary,indent=2))
