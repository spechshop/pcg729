<?php

declare(strict_types=1);

// No runtime build: only pinned source files in a disposable directory.
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use SPC\store\SwooleBufferPatcher;

$archive = $argv[1] ?? ROOT_DIR . '/downloads/swoole-v6.3.0-rc1.tar.gz';
$manifest = json_decode(file_get_contents(ROOT_DIR . '/src/globals/patch/swoole_native_buffer_v630rc1.json'), true, 512, JSON_THROW_ON_ERROR);
$fd = json_decode(file_get_contents(ROOT_DIR . '/src/globals/patch/swoole_socket_fd_lifecycle_v630rc1.json'), true, 512, JSON_THROW_ON_ERROR);
$temporary = sys_get_temp_dir() . '/spc-swoole-test-' . bin2hex(random_bytes(8));
mkdir($temporary);

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new \RuntimeException($message);
    }
}

function command(string $command): void
{
    exec($command . ' 2>&1', $output, $code);
    check($code === 0, implode("\n", $output));
}

function snapshot(string $directory): array
{
    $result = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        $result[substr($file->getPathname(), strlen($directory) + 1)] = file_get_contents($file->getPathname());
    }
    ksort($result);
    return $result;
}

function restore(string $directory, array $files): void
{
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    foreach ($files as $name => $contents) {
        if (!is_dir(dirname($directory . '/' . $name))) {
            mkdir(dirname($directory . '/' . $name), 0777, true);
        }
        file_put_contents($directory . '/' . $name, $contents);
    }
}

function reject(string $directory, string $expected): void
{
    $before = snapshot($directory);
    try {
        SwooleBufferPatcher::apply($directory);
    } catch (SPC\exception\RuntimeException $e) {
        check(str_contains($e->getMessage(), $expected), $e->getMessage());
        check(snapshot($directory) === $before, 'Rejected tree was modified');
        return;
    }
    throw new \RuntimeException('Invalid tree accepted');
}

try {
    $files = array_unique(array_merge(array_keys($manifest['files']), array_keys($manifest['unchanged']), array_keys($fd['files']), array_keys($fd['unchanged'])));
    // Read archive entries without extracting or touching the real source tree.
    $tar = new PharData($archive);
    $original = [];
    foreach ($files as $name) {
        $entry = 'swoole-src-6.3.0-rc1/' . $name;
        if (isset($tar[$entry])) {
            $original[$name] = $tar[$entry]->getContent();
        }
    }
    $cases = 0;
    foreach ([false, true] as $extractionHook) {
        $input = $original;
        if ($extractionHook) {
            $input['config.m4'] = str_replace('PHP_ADD_INCLUDE([$ext_srcdir])', "PHP_ADD_INCLUDE( [\$ext_srcdir] )\n    PHP_ADD_INCLUDE([\$abs_srcdir/ext])", $input['config.m4']);
        }
        restore($temporary, $input);
        // Independently establish that the original Buffer patch still produces
        // precisely its old manifest, before any FD modifications.
        command('cd ' . escapeshellarg($temporary) . ' && patch --batch --fuzz=0 --no-backup-if-mismatch -p1 -i ' . escapeshellarg(ROOT_DIR . '/src/globals/patch/swoole_native_buffer_v630rc1.patch'));
        $buffer = snapshot($temporary);
        foreach ($manifest['files'] as $file => $hashes) {
            check(in_array(hash_file('sha256', $temporary . '/' . $file), $hashes['after'], true), 'Buffer regression: ' . $file);
        }
        check(SwooleBufferPatcher::apply($temporary), 'Buffer-only not upgraded');
        $combined = snapshot($temporary);
        check(!SwooleBufferPatcher::apply($temporary), 'Combined tree reapplied');
        check(snapshot($temporary) === $combined, 'Repeated application changed bytes');
        $cases += 2;

        restore($temporary, $input);
        check(SwooleBufferPatcher::apply($temporary), 'Original not patched');
        check(snapshot($temporary) === $combined, 'Original produced different result');
        $cases++;
        foreach ([$manifest['upgrade'], $manifest['lifecycle_upgrade']] as $upgrade) {
            restore($temporary, $buffer);
            command('cd ' . escapeshellarg($temporary) . ' && patch --batch --fuzz=0 --no-backup-if-mismatch -R -p1 -i ' . escapeshellarg(ROOT_DIR . '/src/globals/patch/' . $upgrade['patch']));
            foreach ($upgrade['files'] as $file => $hashes) {
                $hash = is_file($temporary . '/' . $file) ? hash_file('sha256', $temporary . '/' . $file) : null;
                check(in_array($hash, $hashes, true), 'Invalid historical fixture: ' . $file);
            }
            check(SwooleBufferPatcher::apply($temporary), 'Historical Buffer not upgraded');
            check(snapshot($temporary) === $combined, 'Historical upgrade produced different result');
            $cases++;
        }
        // Every nonempty proper subset of the five FD files is invalid.
        $fdFiles = array_keys($fd['files']);
        for ($mask = 1; $mask < (1 << count($fdFiles)) - 1; $mask++) {
            $mixed = $buffer;
            foreach ($fdFiles as $index => $file) {
                if ($mask & (1 << $index)) {
                    $mixed[$file] = $combined[$file];
                }
            }
            restore($temporary, $mixed);
            reject($temporary, 'partially applied');
            $cases++;
        }
        $partial = $input;
        $partial['ext-src/swoole_socket_coro.cc'] = $buffer['ext-src/swoole_socket_coro.cc'];
        restore($temporary, $partial);
        reject($temporary, 'partially applied');
        $cases++;
        foreach ([$input, $buffer, $combined] as $state) {
            $state['src/coroutine/socket.cc'] .= "\n// incompatible edit\n";
            restore($temporary, $state);
            reject($temporary, 'incompatible src/coroutine/socket.cc');
            $cases++;
        }
    }
    // A failing later hunk must not leave an earlier hunk applied: dry-run
    // must stop on patch's real exit status, independent of manifest checks.
    restore($temporary, ['sample' => "one\ntwo\nthree\n"]);
    $badPatch = $temporary . '/invalid.patch';
    file_put_contents($badPatch, "--- a/sample\n+++ b/sample\n@@ -1 +1 @@\n-one\n+ONE\n@@ -3 +3 @@\n-missing\n+THREE\n");
    $before = snapshot($temporary);
    $method = new ReflectionMethod(SwooleBufferPatcher::class, 'applyPatch');
    $method->setAccessible(true);
    $rejected = false;
    try {
        $method->invoke(null, $badPatch, $temporary);
    } catch (SPC\exception\RuntimeException $e) {
        $rejected = str_contains($e->getMessage(), 'Swoole patch failed');
    }
    check($rejected, 'Rejected hunk exit status was ignored');
    check(snapshot($temporary) === $before, 'Dry-run rejection modified input');
    echo "PASS: {$cases} source-state cases; both config.m4 variants; rejected-hunk dry-run; no runtime compilation\n";
} finally {
    restore($temporary, []);
    rmdir($temporary);
}
