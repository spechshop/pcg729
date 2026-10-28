#!/usr/bin/env python3
"""Exercise the real PHP patcher on pinned upstream/old/current/invalid trees."""
from pathlib import Path
import hashlib
import json
import shutil
import subprocess
import tarfile
import tempfile

OUT = Path(__file__).resolve().parent
ROOT = OUT.parents[2]
PATCH = ROOT / 'src/globals/patch'
manifest = json.loads((PATCH / 'swoole_native_buffer_v630rc1.json').read_text())
names = set(manifest['files']) | set(manifest['unchanged'])
rows = []
php = str(ROOT / 'buildroot/bin/php')

def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest() if path.exists() else None

def run(tree, expected):
    before = {n: digest(tree / n) for n in names}
    p = subprocess.run([php, '-n', str(OUT / 'apply_patch.php'), str(tree)], capture_output=True, text=True)
    after = {n: digest(tree / n) for n in names}
    if expected == 'REJECT':
        assert p.returncode == 1 and before == after, (p.stdout, p.stderr)
    else:
        assert p.returncode == 0 and p.stdout.endswith(expected + '\n'), (p.stdout, p.stderr)
        assert all(after[n] in v['after'] for n, v in manifest['files'].items())
        assert all(after[n] == h for n, h in manifest['unchanged'].items())
    rows.append(dict(case=tree.name, expected=expected, exit=p.returncode, stdout=p.stdout, stderr=p.stderr))

def old_patch(revision):
    return subprocess.check_output(['git', 'show', revision + ':src/globals/patch/swoole_native_buffer_v630rc1.patch'])

def patch(tree, data):
    p = subprocess.run(['patch', '--batch', '--fuzz=0', '-p1'], cwd=tree, input=data, capture_output=True)
    assert p.returncode == 0, p.stdout.decode() + p.stderr.decode()

with tempfile.TemporaryDirectory(prefix='pcg729-lifecycle-') as tmp:
    tmp = Path(tmp)
    pristine = tmp / 'pristine'
    pristine.mkdir()
    with tarfile.open(ROOT / 'downloads/swoole-v6.3.0-rc1.tar.gz') as archive:
        for m in archive:
            name = m.name.partition('/')[2]
            if name in names:
                f = pristine / name
                f.parent.mkdir(parents=True, exist_ok=True)
                f.write_bytes(archive.extractfile(m).read())
    for variant in ['upstream', 'spc-hook']:
        tree = tmp / variant
        shutil.copytree(pristine, tree)
        if variant == 'spc-hook':
            f = tree / 'config.m4'
            f.write_text(f.read_text().replace('PHP_ADD_INCLUDE([$ext_srcdir])',
                'PHP_ADD_INCLUDE( [$ext_srcdir] )\n    PHP_ADD_INCLUDE([$abs_srcdir/ext])'))
        for revision, label in [('290b4322', 'v1'), ('9a0adee8', 'v2')]:
            previous = tmp / (variant + '-' + label)
            shutil.copytree(tree, previous)
            patch(previous, old_patch(revision))
            if variant == 'upstream':
                baseline = OUT / 'baseline'
                baseline.mkdir(exist_ok=True)
                shutil.copyfile(previous / 'ext-src/swoole_socket_coro.cc', baseline / ('swoole_socket_coro.cc' if label == 'v2' else 'v1_socket.cc'))
            partial = tmp / (variant + '-' + label + '-partial')
            shutil.copytree(previous, partial)
            (partial / 'ext-src/swoole_buffer.cc').write_bytes((tree / 'ext-src/php_swoole.cc').read_bytes())
            run(partial, 'REJECT')
            run(previous, 'APPLIED')
            run(previous, 'UNCHANGED')
        partial = tmp / (variant + '-mixed-valid-hashes')
        shutil.copytree(tree, partial)
        (partial / 'ext-src/php_swoole_buffer.h').write_bytes((tmp / (variant + '-v2') / 'ext-src/php_swoole_buffer.h').read_bytes())
        run(partial, 'REJECT')
        run(tree, 'APPLIED')
        run(tree, 'UNCHANGED')
        for n in ['ext-src/swoole_socket_coro.cc', 'src/coroutine/socket.cc']:
            invalid = tmp / (variant + '-tampered-' + Path(n).stem)
            shutil.copytree(tree, invalid)
            with (invalid / n).open('a') as f:
                f.write('\n// incompatible local change\n')
            run(invalid, 'REJECT')
    # Integrity failure is tested against a separate patch directory, never by
    # mutating versioned assets in place (including when tests run concurrently).
    fake = tmp / 'fake-root'
    shutil.copytree(PATCH, fake / 'src/globals/patch')
    for filename in ['swoole_native_buffer_v630rc1.patch', manifest['upgrade']['patch'], manifest['lifecycle_upgrade']['patch']]:
        path = fake / 'src/globals/patch' / filename
        original = path.read_bytes()
        path.write_bytes(original + b'\n')
        script = tmp / 'integrity.php'
        script.write_text('<?php\ndefine("ROOT_DIR", ' + json.dumps(str(fake)) + ');\n'
            + 'require ' + json.dumps(str(ROOT / 'src/SPC/exception/RuntimeException.php')) + ';\n'
            + 'require ' + json.dumps(str(ROOT / 'src/SPC/store/FileSystem.php')) + ';\n'
            + 'require ' + json.dumps(str(ROOT / 'src/SPC/store/SwooleBufferPatcher.php')) + ';\n'
            + 'try { SPC\\store\\SwooleBufferPatcher::apply($argv[1]); exit(2); } '
              'catch (Throwable $e) { echo $e->getMessage(), "\\n"; exit(1); }')
        p = subprocess.run([php, '-n', str(script), str(pristine)], capture_output=True, text=True)
        assert p.returncode == 1 and 'compatibility manifest' in p.stdout, p
        rows.append(dict(case='integrity-' + filename, expected='REJECT', exit=p.returncode, stdout=p.stdout))
        path.write_bytes(original)
(OUT / 'results/patches.json').write_text(json.dumps(rows, indent=2) + '\n')
print(len(rows), 'patch application, upgrade, idempotence and rejection checks PASS')
