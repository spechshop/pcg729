#!/usr/bin/env python3
import pathlib,json,csv,statistics,itertools,math
P=pathlib.Path(__file__).resolve().parent
rows=[]
for d in sorted(P.glob('stat-r*'))+sorted(P.glob('two-*')):
 if not (d/'manifest.json').is_file():continue
 m=json.loads((d/'manifest.json').read_text())
 if m['status']!='complete':continue
 x=json.loads((d/'measurement.json').read_text())
 counters={}
 for line in (d/'stat.csv').read_text().splitlines():
  if not line or line.startswith('#'):continue
  f=line.split(';')
  if len(f)>=3:
   try:counters[f[2]]=float(f[0])
   except ValueError:pass
 r0=x['start_receiver']['rusage'];r1=x['end_receiver']['rusage']
 def elapsed(k):return (r1[k+'.tv_sec']-r0[k+'.tv_sec'])*1000+(r1[k+'.tv_usec']-r0[k+'.tv_usec'])/1000
 ms=counters['task-clock'];n=m['counts']['rtp'];sec=x['active_seconds']
 row=dict(run=d.name,scenario=m['scenario'],round=int(d.name.split('-')[1][1:]) if d.name.startswith('stat-r') else 0,
          task_clock_ms=ms,cpu_percent=ms/sec/10,user_ms=elapsed('ru_utime'),kernel_ms=elapsed('ru_stime'),
          context_switches=counters['context-switches'],cpu_migrations=counters['cpu-migrations'],
          cycles=counters['cpu_core/cycles/'],instructions=counters['cpu_core/instructions/'],
          rtp=n,audio=m['counts']['audio'],dtmf_packets=m['counts']['dtmf_packets'],dtmf_events=m['counts']['dtmf_events'],dtmf_callbacks=m['counts']['dtmf_callbacks'],
          cpu_us_per_rtp=ms*1000/n,active_seconds=sec,snapshot_seconds=(x['end_receiver']['monotonic_ns']-x['start_receiver']['monotonic_ns'])/1e9,
          two_members=m['two_members'],sink_pcmu_valid=m['counts']['sender']['sink_pcmu_valid'])
 rows.append(row)
if rows:
 with (P/'individual.csv').open('w') as f:
  w=csv.DictWriter(f,fieldnames=rows[0].keys());w.writeheader();w.writerows(rows)
(P/'individual.json').write_text(json.dumps(rows,indent=2))
groups={}
for s in ['baseline-native','optimized-native','optimized-slice']:
 a=[r for r in rows if r['scenario']==s and not r['two_members']]
 if not a:continue
 groups[s]={}
 for key in ['task_clock_ms','cpu_percent','user_ms','kernel_ms','cpu_us_per_rtp','cycles','instructions','context_switches']:
  v=[r[key] for r in a];groups[s][key]=dict(n=len(v),median=statistics.median(v),min=min(v),max=max(v),mean=statistics.mean(v),stdev=statistics.stdev(v) if len(v)>1 else None)
(P/'aggregates.json').write_text(json.dumps(groups,indent=2))
comparisons={}
for name,old,new in [('A','baseline-native','optimized-native'),('B','optimized-slice','optimized-native')]:
 a={r['round']:r['cpu_us_per_rtp'] for r in rows if r['scenario']==old and not r['two_members']}
 b={r['round']:r['cpu_us_per_rtp'] for r in rows if r['scenario']==new and not r['two_members']}
 keys=sorted(a.keys()&b.keys())
 if not keys:continue
 diff=[a[k]-b[k] for k in keys];n=len(diff);mean=statistics.mean(diff)
 # Exact paired sign randomization, two-sided. Exploratory only with n=5.
 distribution=[abs(sum(x*s for x,s in zip(diff,signs))/n) for signs in itertools.product([-1,1],repeat=n)]
 prob=sum(t>=abs(mean)-1e-12 for t in distribution)/len(distribution)
 comparisons[name]=dict(old=old,new=new,paired_rounds=keys,difference_us_per_rtp=diff,mean_difference=mean,median_difference=statistics.median(diff),
  median_paired_reduction_percent=statistics.median([(a[k]-b[k])/a[k]*100 for k in keys]),two_sided_sign_randomization_p=prob)
(P/'comparisons.json').write_text(json.dumps(comparisons,indent=2))
print(json.dumps(dict(aggregates=groups,comparisons=comparisons),indent=2))
