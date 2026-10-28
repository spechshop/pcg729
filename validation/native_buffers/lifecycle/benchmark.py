#!/usr/bin/env python3
"""Short paired comparison; identical fixtures, flags, CPUs and sender."""
from pathlib import Path
import hashlib
import json
import statistics
import subprocess

OUT = Path(__file__).resolve().parent
rows = []
for size in [172, 2048]:
    for repeat in range(7):
        for variant in (['before', 'after'] if repeat % 2 == 0 else ['after', 'before']):
            binary = OUT / ('php-' + variant)
            command = ['taskset', '-c', '2', str(binary), '-n', str(OUT.parent / 'udp_bench.php'),
                'recvfromInto', str(size), '300000', '32', '0', '4', '0']
            p = subprocess.run(command, capture_output=True, text=True, timeout=30, check=True)
            row = json.loads(p.stdout)
            assert row['errors'] == row['integrity_errors'] == row['loss'] == row['sender_exit'] == 0
            row.update(variant=variant, repeat=repeat, command=command)
            rows.append(row)
        print(size, repeat, 'paired run done', flush=True)
(OUT / 'results/benchmark.json').write_text(json.dumps(rows, indent=2) + '\n')
summary = {}
for size in [172, 2048]:
    summary[size] = {}
    for variant in ['before', 'after']:
        values = [r for r in rows if r['size'] == size and r['variant'] == variant]
        summary[size][variant] = {k: dict(median=statistics.median(r[k] for r in values),
            min=min(r[k] for r in values), max=max(r[k] for r in values))
            for k in ['pps', 'us_packet', 'cpu_s_million']}
    summary[size]['cpu_change_percent'] = 100 * (summary[size]['after']['cpu_s_million']['median'] /
        summary[size]['before']['cpu_s_million']['median'] - 1)
summary['sha256'] = {v: hashlib.sha256((OUT / ('php-' + v)).read_bytes()).hexdigest()
    for v in ['before', 'after']}
(OUT / 'results/benchmark-summary.json').write_text(json.dumps(summary, indent=2) + '\n')
print(json.dumps(summary, indent=2))
