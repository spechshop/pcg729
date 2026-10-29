#!/usr/bin/env python3
"""Memcheck the current production objects relinked with test-only fixtures.

Usage: python3 validation/binary_slice/validate_memory.py [valgrind]
Requires build_fixtures.py first. Outputs stay under ignored dist/.
ZTS control retains the same UDP work but replaces slices with existing copies.
"""
from pathlib import Path
import json, os, re, subprocess, sys, tempfile
ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'dist/v1.1.5-validation'
PS=Path('/home/lotus/projetos/psampler')
VG=sys.argv[1] if len(sys.argv)>1 else '/tmp/pcg729-valgrind/root/usr/bin/valgrind'
env=dict(os.environ, VALGRIND_LIB=str(Path(VG).parents[1]/'libexec/valgrind'))
rows=[]
names=['binary_slice','binary_slice_provider','binary_provider_bailout','binary_provider_guards','binary_slice_swoole','binary_slice_oom','zts','zts-control']
for name in names:
    if name.startswith('zts'):
        code=(ROOT/'validation/binary_slice/zts.php').read_text()
        if name.endswith('control'):
            code=code.replace('psampler_binary_slice($source, 4)', 'substr($source->peek($source->length()),4)').replace('psampler_binary_slice($source,4)', 'substr($source->toString(),4)')
    else:
        code=re.search(r'--FILE--\n(.*?)\n--EXPECT',(PS/f'tests/{name}.phpt').read_text(),re.S)[1]
    log=OUT/f'{name}-memcheck.txt'
    with tempfile.NamedTemporaryFile(mode='w',suffix='.php',dir=OUT) as script:
        script.write(code);script.flush()
        args=[VG,'--soname-synonyms=somalloc=*','--leak-check=full','--errors-for-leak-kinds=definite,indirect','--error-exitcode=97','--log-file='+str(log),str(ROOT/'validation/native_buffers/php-fixtures'),'-n','-d','memory_limit=24M',script.name]
        r=subprocess.run(args,env=dict(env,USE_ZEND_ALLOC='1' if name.endswith('oom') else '0'),capture_output=True,text=True,timeout=120)
    t=log.read_text()
    def number(pattern):
        m=re.search(pattern,t)
        return int(m[1].replace(',','')) if m else None
    row=dict(case=name,exit=r.returncode,allocs=number(r'total heap usage: ([\d,]+) allocs'),errors=number(r'ERROR SUMMARY: ([\d,]+) errors'),definitely_lost=number(r'definitely lost: ([\d,]+) bytes'),indirectly_lost=number(r'indirectly lost: ([\d,]+) bytes'),invalid_access=bool(re.search(r'Invalid (read|write|free)|uninitialised|uninitialized|Mismatched free',t)),stdout=r.stdout,stderr=r.stderr,zend_allocator=name.endswith('oom'))
    rows.append(row)
    (OUT/'memory-final.json').write_text(json.dumps(rows,indent=2)+'\n')
    print(name,row['exit'],row['errors'],row['definitely_lost'],flush=True)
    assert row['allocs']>10000 and not row['invalid_access'],row
# OOM intentionally uses Zend's allocator to exercise the memory_limit bailout.
# Other cases expose individual PHP allocations to Memcheck.
for row in rows[:6]:
    assert row['errors']==0 and row['exit']==(255 if row['case'].endswith('oom') else 0),row
assert 'slice allocation bailout cleanup OK' in rows[5]['stdout']
assert all(rows[-1][k]==rows[-2][k] for k in ['errors','definitely_lost','indirectly_lost'])
def loss_contexts(name):
    text=(OUT/f'{name}-memcheck.txt').read_text()
    text=re.sub(r'==\d+== ?', '', text)
    text=re.sub(r'0x[0-9A-Fa-f]+', 'ADDRESS', text)
    text=re.sub(r'loss record \d+ of \d+', 'loss record N', text)
    return re.findall(r'(?m)^ *\d[\d,]* bytes in [\d,]+ blocks are definitely lost[^\n]*\n(?:[^\n]+\n)*', text)
assert loss_contexts('zts') == loss_contexts('zts-control')
print('ZTS loss allocation stacks match the control',flush=True)
