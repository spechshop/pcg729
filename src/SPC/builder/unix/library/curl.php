<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait curl
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        $extra = '';
        // lib:openssl
        $extra .= $this->builder->getLib('openssl') ? '-DCURL_USE_OPENSSL=ON ' : '-DCURL_USE_OPENSSL=OFF -DCURL_ENABLE_SSL=OFF ';
        // lib:brotli
        $extra .= $this->builder->getLib('brotli') ? '-DCURL_BROTLI=ON ' : '-DCURL_BROTLI=OFF ';
        // lib:libssh2
        $libssh2 = $this->builder->getLib('libssh2');
        if ($this->builder->getLib('libssh2')) {
            /* @phpstan-ignore-next-line */
            $extra .= '-DCURL_USE_LIBSSH2=ON ' .
                '-DLIBSSH2_LIBRARY="' . $libssh2->getStaticLibFiles(style: 'cmake') . '" ' .
                '-DLIBSSH2_INCLUDE_DIR="' . BUILD_INCLUDE_PATH . '" ';
        } else {
            $extra .= '-DCURL_USE_LIBSSH2=OFF ';
        }
        // lib:nghttp2
        if ($nghttp2 = $this->builder->getLib('nghttp2')) {
            $extra .= '-DUSE_NGHTTP2=ON ' .
                /* @phpstan-ignore-next-line */
                '-DNGHTTP2_LIBRARY="' . $nghttp2->getStaticLibFiles(style: 'cmake') . '" ' .
                '-DNGHTTP2_INCLUDE_DIR="' . BUILD_INCLUDE_PATH . '" ';
        } else {
            $extra .= '-DUSE_NGHTTP2=OFF ';
        }
        // lib:ldap
        $extra .= $this->builder->getLib('ldap') ? '-DCURL_DISABLE_LDAP=OFF ' : '-DCURL_DISABLE_LDAP=ON ';
        // lib:zstd
        $extra .= $this->builder->getLib('zstd') ? '-DCURL_ZSTD=ON ' : '-DCURL_ZSTD=OFF ';
        // lib:idn2
        $extra .= $this->builder->getLib('idn2') ? '-DUSE_LIBIDN2=ON ' : '-DUSE_LIBIDN2=OFF ';
        // lib:psl
        $extra .= $this->builder->getLib('psl') ? '-DCURL_USE_LIBPSL=ON ' : '-DCURL_USE_LIBPSL=OFF ';
        // lib:libcares
        $extra .= $this->builder->getLib('libcares') ? '-DENABLE_ARES=ON ' : '';

        // Limpar diretório de build
        FileSystem::resetDir($this->source_dir . '/build');

        // Preparar variáveis de ambiente
        $env = [
            'CFLAGS' => $this->getLibExtraCFlags(),
            'LDFLAGS' => $this->getLibExtraLdFlags(),
            'LIBS' => $this->getLibExtraLibs(),
            'PKG_CONFIG_PATH' => BUILD_LIB_PATH . '/pkgconfig',
        ];

        // Configurar CMake com todas as opções necessárias para build estática
        $cmake_args = $this->builder->makeCmakeArgs() . ' ' .
            '-DBUILD_SHARED_LIBS=OFF ' .
            '-DBUILD_STATIC_LIBS=ON ' .
            '-DBUILD_CURL_EXE=OFF ' .
            '-DBUILD_LIBCURL_DOCS=OFF ' .
            '-DBUILD_TESTING=OFF ' .
            '-DCMAKE_POSITION_INDEPENDENT_CODE=ON ' .
            '-DHTTP_ONLY=OFF ' .
            '-DCURL_CA_BUNDLE=none ' .
            '-DCURL_CA_PATH=none ' .
            '-DCMAKE_SKIP_INSTALL_RPATH=ON ' .
            '-DCMAKE_INSTALL_LIBDIR=lib ' .
            $extra;



        // Executar build
        shell()->cd($this->source_dir . '/build')
            ->setEnv($env)
            ->execWithEnv("cmake {$cmake_args} -DCURL_LIBDIRS=" . BUILD_LIB_PATH . " -DCURL_SUPPORT_LIBDIR=" . BUILD_LIB_PATH . " ../")
            ->execWithEnv("cmake --build . -j{$this->builder->concurrency}")
            ->execWithEnv('cmake --install .');

        // Verificar se libcurl.a foi criada
        $lib_path = BUILD_LIB_PATH . '/libcurl.a';
        if (!file_exists($lib_path)) {
            throw new RuntimeException("Failed to build static libcurl: {$lib_path} not found");
        }

        // Patch pkgconf
        $this->patchPkgconfPrefix(['libcurl.pc']);

        // Patch cmake targets se existir
        $cmake_targets = BUILD_LIB_PATH . '/cmake/CURL/CURLTargets-release.cmake';
        if (file_exists($cmake_targets)) {

            shell()->cd(BUILD_LIB_PATH . '/cmake/CURL/')
                ->exec("sed -ie 's|\"/lib/libcurl.a\"|\"" . BUILD_LIB_PATH . "/libcurl.a\"|g' CURLTargets-release.cmake");
        }
    }
}
