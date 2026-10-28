#!/usr/bin/env python3
import json
import re
import tempfile
from pathlib import Path
from memcheck import OUT, ROOT, rows, run
rows.extend(r for r in json.loads((OUT/'results/memcheck.json').read_text())
    if r['case'] not in ['frame_control','zts','zts_bailout','zts_control','zts_fatal_control','contract']
    and not (r['case'] == 'functional' and r['variant'] == 'before'))
for variant in ['before','after']:
    run('frame_control', OUT/'frame_control.php', variant)
    run('zts', OUT/'zts.php', variant)
    run('zts_control', OUT.parent/'zts_control.php', variant)
    run('zts_fatal_control', OUT/'zts_fatal_control.php', variant)
run('zts_bailout', OUT/'zts_bailout.php')
run('functional', OUT.parent/'functional.inc', 'before')
path = ROOT/'tests/native_swoole_buffer/psampler_contract.phpt'
code = re.search(r'--FILE--\n(.*?)\n--EXPECT', path.read_text(), re.S)[1]
with tempfile.NamedTemporaryFile(mode='w', dir=path.parent, suffix='.php', prefix='.memcheck-', delete=False) as f:
    f.write(code)
    tmp = Path(f.name)
try:
    run('contract', tmp, 'contract')
finally:
    tmp.unlink()
