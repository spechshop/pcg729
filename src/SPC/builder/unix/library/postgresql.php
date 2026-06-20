<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait postgresql
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        $builddir = BUILD_ROOT_PATH;
        $envs = '';
        $packages = 'zlib openssl readline libxml-2.0';
        $optional_packages = [
            'zstd' => 'libzstd',
            // 'ldap' => 'ldap',
            'libxslt' => 'libxslt',
            'icu' => 'icu-i18n',
        ];
        $error_exec_cnt = 0;

        foreach ($optional_packages as $lib => $pkg) {
            if ($this->getBuilder()->getLib($lib)) {
                $packages .= ' ' . $pkg;
                $output = shell()->execWithResult("pkg-config --static {$pkg}");
                $error_exec_cnt += $output[0] === 0 ? 0 : 1;
                logger()->info(var_export($output[1], true));
            }
        }

        $output = shell()->execWithResult("pkg-config --cflags-only-I --static {$packages}");
        $error_exec_cnt += $output[0] === 0 ? 0 : 1;
        if (!empty($output[1][0])) {
            $cppflags = $output[1][0];
            $envs .= " CPPFLAGS=\"{$cppflags} -fPIC -fPIE -fno-ident\"";
        }
        $output = shell()->execWithResult("pkg-config --libs-only-L --static {$packages}");
        $error_exec_cnt += $output[0] === 0 ? 0 : 1;
        if (!empty($output[1][0])) {
            $ldflags = $output[1][0];
            $envs .= !($this instanceof LinuxLibraryBase) || getenv('SPC_LIBC') === 'glibc' ? " LDFLAGS=\"{$ldflags}\" " : " LDFLAGS=\"{$ldflags} -static\" ";
        }
        $output = shell()->execWithResult("pkg-config --libs-only-l --static {$packages}");
        $error_exec_cnt += $output[0] === 0 ? 0 : 1;
        if (!empty($output[1][0])) {
            $libs = $output[1][0];
            $libcpp = '';
            if ($this->builder->getLib('icu')) {
                $libcpp = $this instanceof LinuxLibraryBase ? ' -lstdc++' : ' -lc++';
            }
            $envs .= " LIBS=\"{$libs}{$libcpp}\" ";
        }
        if ($error_exec_cnt > 0) {
            throw new RuntimeException('Failed to get pkg-config information!');
        }

        FileSystem::resetDir($this->source_dir . '/build');

        $version = $this->getVersion();
        // 16.1 workaround
        if (version_compare($version, '16.1') >= 0) {
            # 有静态链接配置  参考文件： src/interfaces/libpq/Makefile
            shell()->cd($this->source_dir . '/build')
                ->exec('sed -i.backup "s/invokes exit\'; exit 1;/invokes exit\';/"  ../src/interfaces/libpq/Makefile')
                ->exec('sed -i.backup "278 s/^/# /"  ../src/Makefile.shlib')
                ->exec('sed -i.backup "402 s/^/# /"  ../src/Makefile.shlib');
        } else {
            throw new RuntimeException('Unsupported version for postgresql: ' . $version . ' !');
        }

        // configure
        shell()->cd($this->source_dir . '/build')
            ->exec(
                "{$envs} ../configure " .
                "--prefix={$builddir} " .
                '--disable-thread-safety ' .
                '--enable-coverage=no ' .
                '--with-ssl=openssl ' .
                '--with-readline ' .
                '--with-libxml ' .
                ($this->builder->getLib('icu') ? '--with-icu ' : '--without-icu ') .
                '--without-ldap ' .
                ($this->builder->getLib('libxslt') ? '--with-libxslt ' : '--without-libxslt ') .
                ($this->builder->getLib('zstd') ? '--with-zstd ' : '--without-zstd ') .
                '--without-lz4 ' .
                '--without-perl ' .
                '--without-python ' .
                '--without-pam ' .
                '--without-bonjour ' .
                '--without-tcl '
            );
        // ($this->builder->getLib('ldap') ? '--with-ldap ' : '--without-ldap ') .

        // build
        shell()->cd($this->source_dir . '/build')
            ->exec($envs . ' make -C src/bin/pg_config install')
            ->exec($envs . ' make -C src/include install')
            ->exec($envs . ' make -C src/common install')
            ->exec($envs . ' make -C src/port install')
            ->exec($envs . ' make -C src/interfaces/libpq all-static-lib')
            ->exec($envs . ' make -C src/interfaces/libpq install-lib-static')
            ->exec($envs . ' make -C src/interfaces/libpq install');

            // libpq headers are not always installed correctly by the partial libpq install.
            if (!is_dir(BUILD_INCLUDE_PATH . '/libpq')) {
                mkdir(BUILD_INCLUDE_PATH . '/libpq', 0777, true);
            }
            if (!is_dir(BUILD_INCLUDE_PATH . '/postgresql')) {
                mkdir(BUILD_INCLUDE_PATH . '/postgresql', 0777, true);
            }

            copy($this->source_dir . '/src/interfaces/libpq/libpq-fe.h', BUILD_INCLUDE_PATH . '/libpq-fe.h');
            copy($this->source_dir . '/src/include/postgres_ext.h', BUILD_INCLUDE_PATH . '/postgres_ext.h');

            if (file_exists($this->source_dir . '/src/interfaces/libpq/libpq-events.h')) {
                copy($this->source_dir . '/src/interfaces/libpq/libpq-events.h', BUILD_INCLUDE_PATH . '/libpq-events.h');
            }

            copy($this->source_dir . '/src/include/libpq/libpq-fs.h', BUILD_INCLUDE_PATH . '/libpq/libpq-fs.h');
            copy($this->source_dir . '/src/include/libpq/libpq-fs.h', BUILD_INCLUDE_PATH . '/postgresql/libpq-fs.h');

            // PostgreSQL 16+ static libpq may reference public encoding symbols, while
            // libpgcommon.a provides the private frontend variants. Provide a tiny compat archive.
            $compat_c = <<<'C'
            extern int pg_char_to_encoding_private(const char *name);
            extern const char *pg_encoding_to_char_private(int encoding);

            int pg_char_to_encoding(const char *name)
            {
                return pg_char_to_encoding_private(name);
            }

            const char *pg_encoding_to_char(int encoding)
            {
                return pg_encoding_to_char_private(encoding);
            }
            C;

            FileSystem::writeFile($this->source_dir . '/build/libpq_encoding_compat.c', $compat_c);

            $cc = getenv('CC') ?: 'x86_64-linux-musl-gcc';
            $ar = getenv('AR') ?: 'ar';
            $ranlib = getenv('RANLIB') ?: 'ranlib';

            shell()->cd($this->source_dir . '/build')
                ->exec(
                    $cc .
                    ' -I' . BUILD_INCLUDE_PATH .
                    ' -I' . BUILD_INCLUDE_PATH . '/postgresql' .
                    ' -c libpq_encoding_compat.c -o libpq_encoding_compat.o'
                )
                ->exec($ar . ' rcs ' . BUILD_LIB_PATH . '/libpq_encoding_compat.a libpq_encoding_compat.o')
                ->exec($ranlib . ' ' . BUILD_LIB_PATH . '/libpq_encoding_compat.a');

            // Some PostgreSQL partial installs do not generate libpq.pc. PHP configure
            // prefers pkg-config for libpq detection, so generate a static-safe one.
            if (!is_dir(BUILD_LIB_PATH . '/pkgconfig')) {
                mkdir(BUILD_LIB_PATH . '/pkgconfig', 0777, true);
            }

            $libpq_pc = <<<PC
            prefix={$builddir}
            exec_prefix=\${prefix}
            libdir=\${prefix}/lib
            includedir=\${prefix}/include

            Name: libpq
            Description: PostgreSQL libpq static client library
            Version: {$version}
            Cflags: -I\${includedir}
            Libs: -L\${libdir} -lpq -lpq_encoding_compat -lpgcommon -lpgport
            Libs.private: -lssl -lcrypto -lxml2 -lz -liconv -lcharset -lreadline -lncurses -lm -ldl -lpthread

            PC;

            FileSystem::writeFile(BUILD_LIB_PATH . '/pkgconfig/libpq.pc', $libpq_pc);

        // remove dynamic libs
        shell()->cd($this->source_dir . '/build')
            ->exec("rm -rf {$builddir}/lib/*.so.*")
            ->exec("rm -rf {$builddir}/lib/*.so")
            ->exec("rm -rf {$builddir}/lib/*.dylib");
    }

    private function getVersion(): string
    {
        try {
            $file = FileSystem::readFile($this->source_dir . '/meson.build');
            if (preg_match("/^\\s+version:\\s?'(.*)'/m", $file, $match)) {
                return $match[1];
            }
            return 'unknown';
        } catch (FileSystemException) {
            return 'unknown';
        }
    }
}
