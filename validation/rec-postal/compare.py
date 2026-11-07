import csv,json,statistics,hashlib
from pathlib import Path
D=Path(__file__).parent
events=json.loads((D/'events.json').read_text())
rows=[]
for e in events:
    t=e['at_ms']; typ=e['type']; sig=e['signal']
    if t==33400: verdict='incorreto: bip de aproximadamente 1400 Hz classificado como fala'
    elif t==33600: verdict='fim do falso episodio de fala, 118 ms apos o bip'
    elif t<=30460:
        verdict='tom correto, atraso de 50 ms' if sig=='narrowband_tone' else ('silencio correto, atraso de 125 ms' if t==30460 else 'silencio correto, atraso de 130 ms')
    elif t==31200: verdict='fala correta; latencia aproximada de 66 ms (55 ms com limiar -42 dBFS)'
    elif t==33020: verdict='fim da fala correto; aproximadamente 94 ms apos envelope -50 dBFS'
    elif t==37260: verdict='bip corretamente classificado como tom; aproximadamente 61 ms de atraso'
    elif t==37540: verdict='silencio correto; aproximadamente 118 ms de atraso'
    elif t==44520: verdict='fala correta; aproximadamente 102 ms apos envelope -50 dBFS'
    elif t in (44720,46700): verdict='segmentacao por queda de atividade; nao representa fim semantico da locucao'
    else: verdict='retomada de atividade na mesma locucao'
    rows.append(dict(instante_ms=t,evento=typ,resultado=sig,observacao=verdict,motivo=e['reason'],detected_at_ms=e['detected_at_ms']))
with (D/'comparison.csv').open('w') as f:
    w=csv.DictWriter(f,fieldnames=list(rows[0])); w.writeheader(); w.writerows(rows)
summary=json.loads((D/'summary.json').read_text())
bench={k:dict(median_ms=statistics.median(v),p95_nearest_rank_ms=sorted(v)[28],minimum_ms=min(v),maximum_ms=max(v),repetitions=len(v)) for k,v in summary['benchmark'].items()}
(D/'benchmark-summary.json').write_text(json.dumps(bench,indent=2))
# Compact SVG: independent acoustic reference vs observed state. No acoustic labels are derived from events.
colors={'narrowband_tone':'#d99500','voice_like':'#1675ba','silence':'#ddd'}
def xpos(t): return 110+t/48760*1280
svg=['<svg xmlns="http://www.w3.org/2000/svg" width="1440" height="290" viewBox="0 0 1440 290">','<rect width="1440" height="290" fill="white"/>','<g font-family="sans-serif" font-size="14" fill="#222">','<text x="25" y="28">rec_postal.wav — referencia acustica independente e estado online</text>']
for t in range(0,49000,5000):
    xx=xpos(t); svg.append(f'<path d="M{xx},45 V240" stroke="#eee"/><text x="{xx-8}" y="260">{t/1000:g}s</text>')
svg+=['<text x="10" y="90">Referencia</text>','<text x="10" y="175">Online</text>']
ref=[(110+5000*i,1110+5000*i,'narrowband_tone') for i in range(6)]+[(30110,30335,'narrowband_tone'),(31134,32926,'voice_like'),(33333,33482,'narrowband_tone'),(37199,37422,'narrowband_tone'),(44418,48760,'voice_like')]
for a,b,s in ref: svg.append(f'<rect x="{xpos(a)}" y="65" width="{xpos(b)-xpos(a)}" height="40" fill="{colors[s]}"/>')
changes=[e for e in events if e['type']=='signal.changed']
for i,e in enumerate(changes):
    end=changes[i+1]['at_ms'] if i+1<len(changes) else 48760
    svg.append(f'<rect x="{xpos(e["at_ms"])}" y="150" width="{xpos(end)-xpos(e["at_ms"])}" height="40" fill="{colors[e["signal"]]}"/>')
svg+=['<text x="110" y="220">Amarelo: tom/bip. Azul: fala. Cinza: silencio online. Referencia de fala inclui pausas internas.</text>','<text x="110" y="282">Nenhum ring.started, ring.ended, ring.cadence_detected ou ring.interrupted emitido.</text>','</g></svg>']
(D/'timeline.svg').write_text('\n'.join(svg))
original=Path('/home/lotus/projetos/libspech/rec_postal.wav')
expected=json.loads((D/'input.json').read_text())['original_sha256']
assert hashlib.sha256(original.read_bytes()).hexdigest()==expected,'Original changed'
print(json.dumps(bench,indent=2)); print('Original SHA256 unchanged:',expected)
