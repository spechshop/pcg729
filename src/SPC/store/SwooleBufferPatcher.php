<?php

declare(strict_types=1);

namespace SPC\store;

use SPC\exception\RuntimeException;

/**
 * Buffer and public read ABI patches for the pinned Swoole sources only.
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
        $patch = $patchDir . self::PATCH_NAME . '.patch';
        if (self::fileHash($patch) !== $manifest['patch_sha256']) {
            throw new RuntimeException('Swoole Buffer patch is missing or differs from its compatibility manifest');
        }
        $upgrades = [$manifest['upgrade'], $manifest['lifecycle_upgrade']];
        foreach ($upgrades as $upgrade) {
            if (self::fileHash($patchDir . $upgrade['patch']) !== $upgrade['patch_sha256']) {
                throw new RuntimeException('Swoole Buffer upgrade patch differs from its compatibility manifest');
            }
        }

        // Also pin the unmodified C++ receive implementation and class helpers.
        // A future release must be reviewed explicitly rather than patched by fuzz.
        foreach ($manifest['unchanged'] as $file => $hash) {
            if (self::fileHash($sourceDir . '/' . $file) !== $hash) {
                throw new RuntimeException("Swoole Buffer patch requires {$manifest['upstream']}: incompatible {$file}");
            }
        }

        $allBefore = true;
        $allAfter = true;
        $allPrevious = array_fill(0, count($upgrades), true);
        foreach ($manifest['files'] as $file => $hashes) {
            $hash = self::fileHash($sourceDir . '/' . $file);
            $before = in_array($hash, $hashes['before'], true);
            $after = in_array($hash, $hashes['after'], true);
            $previous = false;
            foreach ($upgrades as $index => $upgrade) {
                $matches = in_array($hash, $upgrade['files'][$file], true);
                $previous = $previous || $matches;
                $allPrevious[$index] = $allPrevious[$index] && $matches;
            }
            if (!$before && !$after && !$previous) {
                throw new RuntimeException("Swoole Buffer patch requires {$manifest['upstream']}: incompatible {$file}");
            }
            $allBefore = $allBefore && $before;
            $allAfter = $allAfter && $after;
        }
        if ($allAfter) {
            return false;
        }
        $previousIndex = array_search(true, $allPrevious, true);
        if (!$allBefore && $previousIndex === false) {
            throw new RuntimeException('Swoole Buffer patch is partially applied; restore the Swoole source before building');
        }

        // Complete v1.1.3 and read ABI v1 trees can be upgraded without mixed hunks.
        // Pristine, previous and current states are checked as whole trees.
        $selectedPatch = $allBefore ? self::PATCH_NAME . '.patch' : $upgrades[$previousIndex]['patch'];
        if (!SourcePatcher::patchFile($selectedPatch, $sourceDir)) {
            throw new RuntimeException('Swoole Buffer patch could not be applied');
        }

        // patchFile() does not enforce the patch process exit code. Verify every
        // output so a rejected hunk or incomplete write always stops this build.
        foreach ($manifest['files'] as $file => $hashes) {
            if (!in_array(self::fileHash($sourceDir . '/' . $file), $hashes['after'], true)) {
                throw new RuntimeException("Swoole Buffer patch failed for {$file}; restore the Swoole source before building");
            }
        }
        return true;
    }

    private static function fileHash(string $path): ?string
    {
        if (!file_exists($path)) {
            return null;
        }
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("Cannot read Swoole Buffer patch input: {$path}");
        }
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new RuntimeException("Cannot hash Swoole Buffer patch input: {$path}");
        }
        return $hash;
    }
}
