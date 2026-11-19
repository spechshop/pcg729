#!/usr/bin/env python3
import subprocess,json,pathlib,statistics,platform,os,hashlib
root=pathlib.Path(__file__).resolve().parents[3]; out=pathlib.Path(__file__).resolve().parent
bins={'baseline':out/'php-baseline','interned':root/'buildroot/bin/php'}
hashes={k:hashlib.sha256(v.read_bytes()).hexdigest() for k,v in bins.items()}
assert hashes['baseline']!=hashes['interned'], 'Refuse accidental A/A comparison'
symbols=subprocess.check_output(['nm',str(bins['interned'])],text=True)
assert 'rtp_property_names' in symbols, 'Missing optimization in executable'
(out/'bench-binaries.json').write_text(json.dumps(hashes,indent=2))
rows=[]
for repeat in range(9):
 for size in [4,160,1200]:
  for mode in ['legacy','native','raw','slice']:
   for label in (['baseline','interned'] if repeat%2==0 else ['interned','baseline']):
    cmd=['taskset','-c','2',str(bins[label]),str(out/'bench.php'),mode,str(size),'swoole']
    p=subprocess.run(cmd,check=True,text=True,capture_output=True)
    row=json.loads(p.stdout); row.update(binary=label,repeat=repeat); rows.append(row)
(out/'bench.json').write_text(json.dumps(rows,indent=2))
lines=['binary,mode,payload,n_repeats,cpu_ns_median,cpu_ns_min,cpu_ns_max,cpu_ns_stdev']
for size in [4,160,1200]:
 for mode in ['legacy','native','raw','slice']:
  for label in bins:
   a=[r['cpu_ns'] for r in rows if (r['binary'],r['mode'],r['size'])==(label,mode,size)]
   lines.append(f'{label},{mode},{size},{len(a)},{statistics.median(a):.2f},{min(a):.2f},{max(a):.2f},{statistics.stdev(a):.2f}')
(out/'summary.csv').write_text('\n'.join(lines)+'\n')
print('\n'.join(lines))
