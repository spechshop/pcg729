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
        }

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

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        $source = $this->getSourceDir();

        $has_cmake = file_exists($source . '/CMakeLists.txt');
        $has_configure = file_exists($source . '/configure');
        $has_makefile_in = file_exists($source . '/Makefile.in');

        shell()->cd($source)
            ->setEnv([
                'CFLAGS'  => trim('-I' . BUILD_INCLUDE_PATH . ' -fPIC ' . $this->getLibExtraCFlags()),
                'LDFLAGS' => trim('-L' . BUILD_LIB_PATH . ' ' . $this->getLibExtraLdFlags()),
                'LIBS'    => $this->getLibExtraLibs(),
            ]);

        if ($has_cmake) {
            echo "🔧 Detected CMake build system for libsoxr\n";

            // garantir diretório build separado
            if (!is_dir($source . '/build')) {
                mkdir($source . '/build', 0755, true);
            }

            shell()->cd($source . '/build')
                ->execWithEnv('cmake .. -DCMAKE_BUILD_TYPE=Release '
                    . '-DCMAKE_INSTALL_PREFIX= '
                    . '-DBUILD_SHARED_LIBS=OFF '
                    . '-DBUILD_TESTS=OFF '
                    . '-DCMAKE_POSITION_INDEPENDENT_CODE=ON '
                    . '-DCMAKE_C_FLAGS="-O2 -fPIC"')
                ->execWithEnv("make -j {$this->builder->concurrency}")
                ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);

        } elseif ($has_configure) {
            echo "🔧 Detected Autotools build for libsoxr\n";

            if (!$has_makefile_in) {
                if (file_exists($source . '/autogen.sh')) {
                    echo "⚙️  Running autogen.sh to generate Makefile.in\n";
                    shell()->execWithEnv('./autogen.sh');
                } else {
                    echo "⚙️  Running autoreconf -fi to generate Makefile.in\n";
                    shell()->execWithEnv('autoreconf -fi');
                }
            }

            shell()->execWithEnv('./configure --prefix= --enable-static --disable-shared --with-pic')
                ->execWithEnv("make -j {$this->builder->concurrency}")
                ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);

        } else {
            throw new RuntimeException('Nenhum sistema de build detectado (sem CMakeLists.txt nem configure)');
        }

        // patchar o .pc se existir
        $pkg_files = glob(BUILD_LIB_PATH . '/pkgconfig/soxr*.pc');
        if (!empty($pkg_files)) {
            $this->patchPkgconfPrefix(array_map('basename', $pkg_files), PKGCONF_PATCH_PREFIX);
        }
    }
}
