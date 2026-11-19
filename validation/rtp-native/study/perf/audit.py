#!/usr/bin/env python3
import pathlib,json,hashlib
P=pathlib.Path(__file__).resolve().parent;ROOT=P.parents[3]
runs=[]
for pattern in ['stat-r*','profile-*','two-*']:
 for d in sorted(P.glob(pattern)):
  if not d.is_dir():continue
  m=json.loads((d/'manifest.json').read_text())
  assert m['status']=='complete',(d,m['status'])
  x=json.loads((d/'measurement.json').read_text())
  for side in ['start_receiver','end_receiver']:
   assert x[side]['binary_sha256']==m['binary_sha256'] and x[side]['pid']==m['pid']
   assert x[side]['real_packet_factory_verified']
  assert 59.99<x['active_seconds']<60.1
  assert abs(m['counts']['rtp']-3000)<=2
  assert m['counts']['audio']==m['counts']['rtp']-220 and m['counts']['dtmf_packets']==220 and m['counts']['dtmf_events']==20
  for path,h in m['hashes'].items():
   assert hashlib.sha256((ROOT/path).read_bytes()).hexdigest()==h,(d,path)
  runs.append(m)
assert sum(not m['profile'] and not m['two_members'] for m in runs)==15
main=[m for m in runs if not m['two_members']]
assert all(m['config']==main[0]['config'] for m in main)
(P/'audit.json').write_text(json.dumps({'validated_runs':len(runs),'stat_runs':15,'packet_counts_within_predeclared_edge_tolerance':True,'config_equal_main':True,'all_hashed_sources_unchanged':True,'runtime_pid_hash_factory_verified':True},indent=2))
print('Audit OK',len(runs))
