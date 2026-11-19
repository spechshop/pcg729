from pathlib import Path
import subprocess,json,statistics
r=Path(__file__).resolve().parents[1];rows=[]
for rep in range(9):
 for v in (['baseline','optimization'] if rep%2==0 else ['optimization','baseline']):
  p=subprocess.run([str(r.parents[1]/'buildroot/bin/php'),str(r/'tests/bench.php'),str(r/'lab'/v),'micro','200000'],capture_output=True,text=True);x=json.loads(p.stdout);x.update(variant=v,repeat=rep);rows.append(x)
(r/'evidence/resultados/micro-corrected.json').write_text(json.dumps(rows,indent=2))
for v in ['baseline','optimization']:
 a=[x['cpu'] for x in rows if x['variant']==v];print(v,statistics.median(a),min(a),max(a))
