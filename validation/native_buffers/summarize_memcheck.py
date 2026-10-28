#!/usr/bin/env python3
from pathlib import Path
import re,json
OUT=Path(__file__).resolve().parent
records=[]
for p in sorted((OUT/'results').glob('memcheck-*.txt')):
 if p.name.endswith('-output.txt') or p.name in ['memcheck-progress.txt','memcheck-tracked-progress.txt']:continue
 t=p.read_text();find=lambda pattern:int(re.search(pattern,t)[1].replace(',',''))if re.search(pattern,t)else None
 r=dict(file=p.name,errors=find(r'ERROR SUMMARY: ([\d,]+) errors'),allocs=find(r'total heap usage: ([\d,]+) allocs'),allocated_bytes=find(r'frees, ([\d,]+) bytes allocated'),definitely_lost=find(r'definitely lost: ([\d,]+) bytes'),indirectly_lost=find(r'indirectly lost: ([\d,]+) bytes'),possibly_lost=find(r'possibly lost: ([\d,]+) bytes'),reachable=find(r'still reachable: ([\d,]+) bytes'),invalid_access_lines=re.findall(r'^.*(?:Invalid read|Invalid write|Invalid free|Mismatched free|uninitialised|uninitialized).*$',t,re.M))
 records.append(r)
(OUT/'results/memcheck-all-summary.json').write_text(json.dumps(records,indent=2)+'\n')
print('logs',len(records),'invalid access matches',sum(len(r['invalid_access_lines'])for r in records))
