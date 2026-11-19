from pathlib import Path
r=Path(__file__).resolve().parents[1]
for v in ['baseline','candidate','optimization']:
 p=r/'lab'/v/'.env'
 if not p.exists(): p.write_text('')
