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
        if ($this->builder instanceof MacOSBuilder) {
            // Fix swoole with event extension <util.h> conflict bug (for MacOS)
            $util_path = shell()->execWithResult('xcrun --show-sdk-path', false)[1][0] . '/usr/include/util.h';
            FileSystem::replaceFileStr(
                SOURCE_PATH . '/php-src/ext/swoole/thirdparty/php/standard/proc_open.cc',
                'include <util.h>',
                'include "' . $util_path . '"'
            );
            return true;
        }
        return false;
    }

    public function getExtVersion(): ?string
    {
        $file = SOURCE_PATH . '/php-src/ext/swoole/include/swoole_version.h';
        $pattern = '/#define SWOOLE_VERSION "(.+)"/';
        if (preg_match($pattern, file_get_contents($file), $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function getUnixConfigureArg(): string
    {
        $arg = '--enable-swoole';

        // Recursos obrigatórios
        $arg .= ' --enable-openssl';      // HTTPS, WebSocket TLS
        $arg .= ' --enable-sockets';       // TCP, UDP, UnixSocket

        // Recursos de rede e otimizações
        $arg .= ' --enable-cares';         // DNS Resolver (c-ares)
        $arg .= ' --enable-swoole-curl';   // Hook nativo do curl

        // Diretórios de bibliotecas externas
        $arg .= $this->builder->getLib('brotli') ? (' --with-brotli-dir=' . BUILD_ROOT_PATH) : '';
        $arg .= $this->builder->getLib('nghttp2') ? (' --with-nghttp2-dir=' . BUILD_ROOT_PATH) : '';

        // Banco de dados (sem depender de "hook" extra)

        //$arg .= ' --enable-swoole-sqlite';
  if ($this->getExtVersion() >= '6.1.0') {
            $arg .= ' --enable-swoole-stdext';
        }
        // Sistema
        $arg .= ' --enable-swoole-thread'; // Fix epoll fd warnings
        $arg .= ' --enable-swoole-posix';  // POSIX suporte (ex: gethostname, signals)

        // Libuv suporte se disponível
        $arg .= $this->builder->getLib('libuv') ? ' --enable-swoole-uv' : '';
        $arg .= ' --enable-pdo';
  $arg .= $this->builder->getOption('enable-zts') ? ' --enable-swoole-thread --disable-thread-context' : ' --disable-swoole-thread --enable-thread-context';

        // required features: curl, openssl (but curl hook is buggy for php 8.0)
        $arg .= $this->builder->getPHPVersionID() >= 80100 ? ' --enable-swoole-curl' : ' --disable-swoole-curl';
        $arg .= ' --enable-openssl';


        return $arg;
    }
}
