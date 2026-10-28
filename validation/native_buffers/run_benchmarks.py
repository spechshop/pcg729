#!/usr/bin/env python3
from pathlib import Path
import subprocess,json,random,time,platform,hashlib,os,sys
OUT=Path(__file__).resolve().parent;ROOT=OUT.parents[1]
PHP=ROOT/'buildroot/bin/php';FIX=OUT/'php-fixtures';RESULTS=OUT/'results'
RECEIVER_CPU=2;SENDER_CPU=4
rng=random.Random(729)
metadata={'utc':time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime()),'platform':platform.platform(),'cpus':os.cpu_count(),'receiver_cpu':RECEIVER_CPU,'sender_cpu':SENDER_CPU,'php_sha256':hashlib.sha256(PHP.read_bytes()).hexdigest(),'fixed_packets':1000000,'repetitions':5,'batch':32,'stress_packets':10000000,'cadence_packets':250,'cadence_pps':50,'byte_operations':2000000,'seed':729}
(RESULTS/'benchmark-environment.json').write_text(json.dumps(metadata,indent=2)+'\n')
previous=[]
if '--udp-only' in sys.argv:
 previous=[json.loads(s)for s in (RESULTS/'benchmarks.jsonl').read_text().splitlines() if json.loads(s)['kind']=='bytebuffer']
raw=(RESULTS/'benchmarks.jsonl').open('w',buffering=1)
for r in previous:raw.write(json.dumps(r)+'\n')
def run(binary,args,phase,rep):
 cmd=['taskset','-c',str(RECEIVER_CPU),str(binary),'-n',*map(str,args)]
 r=subprocess.run(cmd,cwd=ROOT,capture_output=True,text=True,timeout=180)
 if r.returncode:
  (RESULTS/'benchmark-failure.txt').write_text(json.dumps({'cmd':cmd,'exit':r.returncode,'stdout':r.stdout,'stderr':r.stderr},indent=2));raise RuntimeError(f'benchmark failed: {cmd}: {r.returncode}')
 data=json.loads(r.stdout);data.update(phase=phase,rep=rep,command=cmd)
 raw.write(json.dumps(data)+'\n');print(phase,rep,data['kind'],data.get('size'),data['method'],round(data.get('pps',data.get('ops_s',0))),flush=True)
 return data
# Validate binary bytes outside all performance samples.
for size in [160,172,320,1024,2048]:
 for method in ['recvfrom','recvfromInto']:
  run(PHP,[OUT/'udp_bench.php',method,size,10000,32,0,SENDER_CPU,1],'integrity',0)
# Five pairs per size; deterministic shuffled sizes and alternating method order.
for rep in range(1,6):
 sizes=[160,172,320,1024,2048];rng.shuffle(sizes)
 for size in sizes:
  methods=['recvfrom','recvfromInto'];
  if rep%2==0:methods.reverse()
  for method in methods:run(PHP,[OUT/'udp_bench.php',method,size,1000000,32,0,SENDER_CPU,0],'fixed',rep)
# Prolonged main scenario, ten million per method, credit-limited to avoid drops.
for method in ['recvfromInto','recvfrom']:
 run(PHP,[OUT/'udp_bench.php',method,172,10000000,32,0,SENDER_CPU,0],'stress',1)
# 50 pps pacing exclusively in sender; receiver CPU excludes idle wall time.
for rep in range(1,6):
 methods=['recvfrom','recvfromInto'];
 if rep%2==0:methods.reverse()
 for method in methods:run(PHP,[OUT/'udp_bench.php',method,172,250,1,50,SENDER_CPU,0],'cadence',rep)
for rep in ([] if '--udp-only' in sys.argv else range(1,6)):
 for mode in ['steady','preallocated','growth']:
  sizes=[160,172,320,1024,2048];rng.shuffle(sizes)
  for size in sizes:
   methods=['append','appendBuffer'];
   if rep%2==0:methods.reverse()
   for method in methods:run(FIX,[OUT/'byte_bench.php',method,size,2000000,mode],'byte',rep)
raw.close()
