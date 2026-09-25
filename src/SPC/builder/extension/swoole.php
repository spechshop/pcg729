<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\builder\macos\MacOSBuilder;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('swoole')]
class swoole extends Extension
{
    public function patchBeforeMake(): bool
    {
        if (!$this->builder instanceof MacOSBuilder) {
            return false;
        }

        // Fix Swoole with event extension <util.h> conflict bug on macOS.
        $utilPath = shell()->execWithResult(
                'xcrun --show-sdk-path',
                false
            )[1][0] . '/usr/include/util.h';

        FileSystem::replaceFileStr(
            SOURCE_PATH . '/php-src/ext/swoole/thirdparty/php/standard/proc_open.cc',
            'include <util.h>',
            'include "' . $utilPath . '"'
        );

        return true;
    }

    public function getExtVersion(): ?string
    {
        $file = SOURCE_PATH . '/php-src/ext/swoole/include/swoole_version.h';

        if (!file_exists($file)) {
            return null;
        }

        $pattern = '/#define SWOOLE_VERSION "(.+)"/';

        if (preg_match($pattern, file_get_contents($file), $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function getUnixConfigureArg(): string
    {
        $args = [
            '--enable-swoole',

            // Core / networking
            '--enable-openssl',
            '--enable-sockets',
            '--enable-swoole-posix',
            '--enable-swoole-coro-time',
            '--enable-cares',
            '--enable-pdo',

            // Coroutine context:
            // Do NOT use thread-context on NTS.
            // Let Swoole use its ASM context implementation.
            '--disable-thread-context',
        ];

        /*
         * Swoole Thread requires a ZTS PHP build.
         *
         * This is unrelated to the coroutine context implementation.
         * In particular, do not enable --enable-thread-context here.
         */
        if ($this->builder->getOption('enable-zts')) {
            $args[] = '--enable-swoole-thread';
        } else {
            $args[] = '--disable-swoole-thread';
        }

        // PostgreSQL coroutine support.
        if ($this->builder->getExt('pgsql')) {
            $args[] = '--enable-swoole-pgsql';
        } else {
            $args[] = '--disable-swoole-pgsql';
        }

        // cURL hook.
        if ($this->builder->getPHPVersionID() >= 80100) {
            $args[] = '--enable-swoole-curl';
        } else {
            $args[] = '--disable-swoole-curl';
        }

        // Optional libraries.
        if ($this->builder->getLib('brotli')) {
            $args[] = '--with-brotli-dir=' . BUILD_ROOT_PATH;
        }

        if ($this->builder->getLib('nghttp2')) {
            $args[] = '--with-nghttp2-dir=' . BUILD_ROOT_PATH;
        }

        if ($this->builder->getLib('libuv')) {
            $args[] = '--enable-swoole-uv';
        }

        // Swoole >= 6.1
        $version = $this->getExtVersion();

        if ($version !== null && version_compare($version, '6.1.0', '>=')) {
            $args[] = '--enable-swoole-stdext';
        }

        return implode(' ', $args);
    }
}