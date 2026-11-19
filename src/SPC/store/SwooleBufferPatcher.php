<?php

declare(strict_types=1);

namespace SPC\store;

use SPC\exception\RuntimeException;

/**
 * Ordered Buffer/read ABI and socket FD patches for pinned Linux Swoole sources.
 */
final class SwooleBufferPatcher
{
    private const PATCH_NAME = 'swoole_native_buffer_v630rc1';

    public static function apply(string $sourceDir): bool
    {
        $patchDir = ROOT_DIR . '/src/globals/patch/';
        $manifest = json_decode(
            FileSystem::readFile($patchDir . self::PATCH_NAME . '.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $fd = json_decode(
            FileSystem::readFile($patchDir . 'swoole_socket_fd_lifecycle_v630rc1.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        if (!in_array(PHP_OS_FAMILY, $fd['platforms'], true)) {
            throw new RuntimeException('Swoole socket FD lifecycle patch supports Linux only');
        }
        $upgrades = [$manifest['upgrade'], $manifest['lifecycle_upgrade']];
        $patches = [
            ['patch' => self::PATCH_NAME . '.patch', 'patch_sha256' => $manifest['patch_sha256']],
            ...$upgrades,
            $fd,
        ];
        foreach ($patches as $patch) {
            if (self::fileHash($patchDir . $patch['patch']) !== $patch['patch_sha256']) {
                throw new RuntimeException("Swoole patch differs from its compatibility manifest: {$patch['patch']}");
            }
        }

        // Classify the WHOLE verified tree before writing anything. In particular,
        // never accept FD hashes independently in Buffer's `unchanged` section.
        $before = $after = [];
        foreach ($manifest['unchanged'] as $file => $hash) {
            $before[$file] = $after[$file] = [$hash];
        }
        foreach ($manifest['files'] as $file => $hashes) {
            $before[$file] = $hashes['before'];
            $after[$file] = $hashes['after'];
        }
        foreach ($fd['unchanged'] as $file => $hash) {
            $before[$file] = $after[$file] = [$hash];
        }
        foreach ($fd['files'] as $file => $hashes) {
            // Shared files must already be pinned by the Buffer manifest.
            if (isset($after[$file]) && $after[$file] !== $hashes['before']) {
                throw new RuntimeException("Swoole manifests disagree on FD input: {$file}");
            }
            $before[$file] ??= $hashes['before'];
            $after[$file] = $hashes['before'];
        }
        $combined = $after;
        foreach ($fd['files'] as $file => $hashes) {
            $combined[$file] = $hashes['after'];
        }
        $states = ['original' => $before, 'buffer' => $after, 'buffer+fd' => $combined];
        foreach ($upgrades as $index => $upgrade) {
            $states['upgrade-' . $index] = array_replace($before, $upgrade['files']);
        }
        $actual = [];
        foreach ($before as $file => $_) {
            $actual[$file] = self::fileHash($sourceDir . '/' . $file);
        }
        $state = null;
        foreach ($states as $name => $hashes) {
            if (self::matches($actual, $hashes)) {
                $state = $name;
                break;
            }
        }
        if ($state === null) {
            foreach ($actual as $file => $hash) {
                $known = false;
                foreach ($states as $hashes) {
                    $known = $known || in_array($hash, $hashes[$file], true);
                }
                if (!$known) {
                    throw new RuntimeException("Swoole patches require {$manifest['upstream']}: incompatible {$file}; restore the source");
                }
            }
            throw new RuntimeException('Swoole patches are partially applied (mixed states); restore the Swoole source before building');
        }
        if ($state === 'buffer+fd') {
            return false;
        }
        if ($state !== 'buffer') {
            $selected = $state === 'original'
                ? self::PATCH_NAME . '.patch'
                : $upgrades[(int) substr($state, 8)]['patch'];
            self::applyPatch($patchDir . $selected, $sourceDir);
            self::verify($sourceDir, $after);
        }
        self::applyPatch($patchDir . $fd['patch'], $sourceDir);
        self::verify($sourceDir, $combined);
        return true;
    }

    private static function matches(array $actual, array $expected): bool
    {
        foreach ($expected as $file => $hashes) {
            if (!in_array($actual[$file], $hashes, true)) {
                return false;
            }
        }
        return true;
    }

    private static function verify(string $sourceDir, array $expected): void
    {
        foreach ($expected as $file => $hashes) {
            if (!in_array(self::fileHash($sourceDir . '/' . $file), $hashes, true)) {
                throw new RuntimeException("Swoole patch verification failed for {$file}; restore the source before building");
            }
        }
    }

    private static function applyPatch(string $patch, string $sourceDir): void
    {
        // Materialize also when running from a PHAR. No pipeline hides exit status.
        $temporary = tempnam(sys_get_temp_dir(), 'spc-swoole-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot create temporary Swoole patch input');
        }
        try {
            $contents = FileSystem::readFile($patch);
            if (file_put_contents($temporary, $contents) !== strlen($contents)) {
                throw new RuntimeException('Cannot write temporary Swoole patch input');
            }
            $command = 'LC_ALL=C patch --batch --forward --fuzz=0 --no-backup-if-mismatch -p1 -i ' . escapeshellarg($temporary);
            // The extraction hook adds one config.m4 line: offsets are safe
            // only because complete input and output hashes are pinned.
            foreach ([' --dry-run', ''] as $mode) {
                [$code, $output] = shell()->cd($sourceDir)->execWithResult($command . $mode . ' 2>&1');
                if ($code !== 0 || preg_match('/fuzz|reversed|FAILED|Skipping/i', implode("\n", $output))) {
                    throw new RuntimeException('Swoole patch failed (' . basename($patch) . '): ' . implode("\n", $output));
                }
            }
        } finally {
            unlink($temporary);
        }
    }

    private static function fileHash(string $path): ?string
    {
        if (!file_exists($path)) {
            return null;
        }
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("Cannot read Swoole patch input: {$path}");
        }
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new RuntimeException("Cannot hash Swoole patch input: {$path}");
        }
        return $hash;
    }
}
