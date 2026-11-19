from pathlib import Path
import subprocess,json
r=Path(__file__).resolve().parents[1]; php=str(r.parents[1]/'buildroot/bin/php')
rows=[]
for variant in ['baseline','candidate']:
 for mode in ['dtmf','idle','invalid','reentrant']:
  p=subprocess.run([php,str(r/'tests/check.php'),str(r/'lab'/variant),mode],capture_output=True,text=True,timeout=8)
  rows.append(dict(variant=variant,test=mode,exit=p.returncode,stdout=p.stdout,stderr=p.stderr))
(r/'evidence/resultados/functional.json').write_text(json.dumps(rows,indent=2))
print(json.dumps(rows,indent=2))
