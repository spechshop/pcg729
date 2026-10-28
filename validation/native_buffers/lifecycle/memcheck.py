#!/usr/bin/env python3
"""Isolate every fatal; distinguish access errors from baseline fatal/ZTS leaks."""
from pathlib import Path
import json
import os
import re
import subprocess
import tempfile

OUT = Path(__file__).resolve().parent
ROOT = OUT.parents[2]
VG = '/tmp/pcg729-valgrind/root/usr/bin/valgrind'
env = dict(os.environ, VALGRIND_LIB='/tmp/pcg729-valgrind/root/usr/libexec/valgrind', USE_ZEND_ALLOC='0', USE_TRACKED_ALLOC='1')
rows = []
def run(name, script, variant='after', coroutine=False):
    log = OUT / 'results' / ('memcheck-' + name + '-' + variant + '.txt')
    cmd = [VG, '--soname-synonyms=somalloc=*', '--tool=memcheck', '--leak-check=full',
        '--show-leak-kinds=definite,indirect', '--errors-for-leak-kinds=definite,indirect',
        '--error-exitcode=97', '--log-file=' + str(log), str(OUT / ('php-' + variant)), '-n',
        '-d', 'display_errors=1', '-d', 'log_errors=0', '-d', 'error_reporting=24575', str(script)]
    p = subprocess.run(cmd, capture_output=True, text=True, timeout=180,
        env=dict(env, RECV_COROUTINE='1' if coroutine else '0'), cwd=ROOT)
    text = log.read_text()
    def n(pattern):
        match = re.search(pattern, text)
        return int(match[1].replace(',', '')) if match else 0
    row = dict(case=name, variant=variant, command=cmd, coroutine=coroutine, exit=p.returncode,
        allocs=n(r'total heap usage: ([\d,]+) allocs'),
        errors=n(r'ERROR SUMMARY: ([\d,]+) errors'),
        definitely_lost=n(r'definitely lost: ([\d,]+) bytes'),
        indirectly_lost=n(r'indirectly lost: ([\d,]+) bytes'),
        access_errors=bool(re.search(r'Invalid (read|write|free)|uninitialised|uninitialized|Mismatched free', text)),
        stdout=p.stdout, stderr=p.stderr)
    assert row['allocs'] > 10000 and p.returncode >= 0, row
    rows.append(row)
    (OUT / 'results/memcheck.json').write_text(json.dumps(rows, indent=2) + '\n')
    print(name, variant, 'exit', p.returncode, 'errors', row['errors'], 'lost', row['definitely_lost'], flush=True)

def main():
    for path in sorted((ROOT / 'tests/native_swoole_buffer').glob('*.phpt')):
        if path.stem == 'psampler_contract':
            continue
        code = re.search(r'--FILE--\n(.*?)\n--EXPECT', path.read_text(), re.S)[1]
        with tempfile.NamedTemporaryFile(mode='w', dir=path.parent, suffix='.php', prefix='.memcheck-', delete=False) as f:
            f.write(code)
            tmp = Path(f.name)
        try:
            run(path.stem, tmp)
            if path.stem in ['recvfrom_into_bailout', 'recvfrom_into_typed_bailout']:
                run(path.stem + '-coroutine', tmp, coroutine=True)
        finally:
            tmp.unlink()
    for name in ['peer_bailout', 'fatal_coroutine_control']:
        for variant in ['before', 'after']:
            run(name, OUT.parent / (name + '.php'), variant)
    for variant in ['before', 'after']:
        run('frame_control', OUT / 'frame_control.php', variant)
    run('functional', OUT.parent / 'functional.inc')

if __name__ == "__main__":
    main()
