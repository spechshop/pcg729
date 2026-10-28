#!/usr/bin/env python3
from pathlib import Path
import json,statistics,csv,collections
OUT=Path(__file__).resolve().parent
rows=[json.loads(s) for s in (OUT/'results/benchmarks.jsonl').read_text().splitlines()]
groups=collections.defaultdict(list)
for r in rows:
 if r['phase']=='integrity':continue
 key=(r['kind'],r['phase'],r.get('mode',''),r['size'],r['method'])
 groups[key].append(r)
def describe(v):
 q=statistics.quantiles(v,n=4,method='inclusive') if len(v)>1 else [v[0]]*3
 return dict(median=statistics.median(v),min=min(v),max=max(v),q1=q[0],q3=q[2],iqr=q[2]-q[0])
summary=[]
for (kind,phase,mode,size,method),v in sorted(groups.items()):
 s=dict(kind=kind,phase=phase,mode=mode,size=size,method=method,n=len(v))
 fields=['wall_s','cpu_s','cpu_s_million','cpu_percent','php_before','php_after','php_peak','php_peak_real']
 fields+=['pps','us_packet','cpu_user_s','cpu_sys_s'] if kind=='udp' else ['ops_s','us_op','mib_s']
 for f in fields:s[f]=describe([r[f] for r in v])
 for f in ['rss_kib','hwm_kib']:s[f]=describe([r['rss_after'][f] for r in v])
 s['php_delta']=describe([r['php_after']-r['php_before'] for r in v])
 s['rss_delta_kib']=describe([r['rss_after']['rss_kib']-r['rss_before']['rss_kib'] for r in v])
 if kind=='udp':
  for f in ['sent','send_errors','sender_cpu_s','sender_wall_s']:s[f]=describe([r['sender'][f] for r in v])
  s['loss']=sum(r['loss'] for r in v);s['errors']=sum(r['errors'] for r in v);s['received_total']=sum(r['received'] for r in v)
  s['sender_cpu_s_million']=describe([r['sender']['sender_cpu_s']*1e6/r['received'] for r in v])
 else:s['operations_total']=sum(r['count'] for r in v)
 summary.append(s)
changes=[]
for s in summary:
 if s['method'] not in ['recvfromInto','appendBuffer']:continue
 base=next(x for x in summary if (x['kind'],x['phase'],x['mode'],x['size'],x['method'])==(s['kind'],s['phase'],s['mode'],s['size'],'recvfrom' if s['kind']=='udp' else 'append'))
 fields=['cpu_s_million','wall_s','rss_kib','php_peak']+(['pps','us_packet']if s['kind']=='udp' else ['ops_s','us_op','mib_s'])
 d={k:s[k]for k in ['kind','phase','mode','size']}
 for f in fields:d[f+'_percent']=100*(s[f]['median']/base[f]['median']-1)
 a=groups[(s['kind'],s['phase'],s['mode'],s['size'],s['method'])]
 b=groups[(base['kind'],base['phase'],base['mode'],base['size'],base['method'])]
 d['paired_cpu_percent']=describe([100*(x['cpu_s_million']/y['cpu_s_million']-1) for x,y in zip(sorted(a,key=lambda r:r['rep']),sorted(b,key=lambda r:r['rep']))])
 changes.append(d)
(OUT/'results/summary.json').write_text(json.dumps({'summary':summary,'changes':changes},indent=2)+'\n')
with (OUT/'results/samples.csv').open('w') as f:
 fields=['kind','phase','mode','size','method','rep','requested','received','count','wall_s','pps','ops_s','us_packet','us_op','cpu_s','cpu_s_million','cpu_percent','php_before','php_after','php_peak','php_peak_real','loss','errors']
 w=csv.DictWriter(f,fields,extrasaction='ignore');w.writeheader();w.writerows(rows)
for s in summary:
 if s['phase']=='fixed':print(s['size'],s['method'],'pps',round(s['pps']['median']),'cpu_s/M',round(s['cpu_s_million']['median'],6),'us',round(s['us_packet']['median'],6),'n',s['n'])
print('total records',len(rows))
