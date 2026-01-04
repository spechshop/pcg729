<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\RuntimeException;
use SPC\exception\FileSystemException;
use SPC\store\FileSystem;

trait libsoxr
{
    protected function listPath(string $dir): ?array
    {
        $glob = glob($dir . '/*');
        if ($glob === false) {
            return null;
        } else {
            $files = [];
            foreach ($glob as $file) {
                if (is_dir($file)) {
                    $files = array_merge($files, $this->listPath($file));
                } else {
                    $files[] = $file;
                }
            }
            return $files;
        }
    }

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        $enableIn = false;
        if ($enableIn) {
            shell()->cd($this->source_dir)
                ->setEnv([
                    'CFLAGS' => trim(
                        '-I' . BUILD_INCLUDE_PATH . ' ' .
                        '-I' . $this->source_dir . '/include ' .
                        $this->getLibExtraCFlags()
                    ),
                    'LDFLAGS' => trim(
                        '-L' . BUILD_LIB_PATH . ' ' . $this->getLibExtraLdFlags()
                    ),
                    'LIBS' => $this->getLibExtraLibs(),
                ])
                ->execWithEnv('./autogen.sh || autoreconf -fi || true')
                ->execWithEnv('./go')
                ->cd($this->source_dir.'/Release')
               // ->cd('Release')
                ->exec('ls -A')
                ->execWithEnv("make -j {$this->builder->concurrency}")
                ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);

            $this->patchPkgconfPrefix(['soxr.pc'], PKGCONF_PATCH_PREFIX);
            return;
        }

        $source = $this->getSourceDir();
        $all_files = $this->listPath($source);

        $has_configure = file_exists($source . '/go');
        $has_makefile_in = file_exists($source . '/Makefile.in');
        $has_cmake = file_exists($source . '/CMakeLists.txt');

        shell()->cd($source)
            ->setEnv([
                'CFLAGS' => trim('-I' . BUILD_INCLUDE_PATH . ' ' . $this->getLibExtraCFlags()),
                'LDFLAGS' => trim('-L' . BUILD_LIB_PATH . ' ' . $this->getLibExtraLdFlags()),
                'LIBS' => $this->getLibExtraLibs(),
            ]);

        if ($has_cmake) {
            echo "🔧 Detected CMake build system\n";

            shell()->cd($source)
                ->execWithEnv('cmake \
                    -DCMAKE_BUILD_TYPE=Release \
                    -DCMAKE_INSTALL_PREFIX= \
                    -DBUILD_SHARED_LIBS=OFF \
                    -DWITH_OPENMP=OFF \
                    -DBUILD_TESTS=OFF \
                    -DBUILD_EXAMPLES=OFF \
                    -DCMAKE_C_FLAGS="-fPIC" \
                    -DCMAKE_CXX_FLAGS="-fPIC" \
                    .')
                ->execWithEnv("make -j {$this->builder->concurrency}")
                ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);

        } elseif ($has_configure) {
            echo "🔧 Detected Autotools project\n";

            if (!$has_makefile_in) {
                if (file_exists('./autogen.sh')) {
                    echo "⚙️  Running autogen.sh to generate Makefile.in\n";
                    shell()->execWithEnv('./autogen.sh');
                } else {
                    echo "⚙️  Running autoreconf -fi to generate Makefile.in\n";
                    shell()->execWithEnv('autoreconf -fi');
                }
            }

            shell()->execWithEnv('./configure --prefix= --enable-static --disable-shared --with-pic')
                ->execWithEnv("make -j 4")
                ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);

        } else {
            throw new RuntimeException('Nenhum sistema de build detectado (sem configure/Makefile.in ou CMakeLists.txt)');
        }

        $this->patchPkgconfPrefix(['soxr.pc'], PKGCONF_PATCH_PREFIX);
    }
}
