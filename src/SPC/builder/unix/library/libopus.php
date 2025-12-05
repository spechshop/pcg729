<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\RuntimeException;
use SPC\exception\FileSystemException;
use SPC\store\FileSystem;

trait libopus
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
        $enableIn = true;
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
                ->execWithEnv('./configure --prefix= --enable-static --disable-shared --with-pic')
                ->execWithEnv("make -j {$this->builder->concurrency}")
                ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);

            $this->patchPkgconfPrefix(['opus.pc'], PKGCONF_PATCH_PREFIX);
            return;
        }

        $source = $this->getSourceDir();
        $all_files = $this->listPath($source);

        // garantir que diretórios existam
        $include_dir = $source . '/include/opus';
        if (!is_dir($include_dir)) {
            if (!mkdir($include_dir, 0755, true)) {
                throw new FileSystemException("Failed to create directory: $include_dir");
            }
        }

        // copiar headers do src para include (compatível com estrutura padrão do opus)
        $src_dir = $source . '/src';
        if (is_dir($src_dir)) {
            $src_files = $this->listPath($src_dir);
            if ($src_files !== null) {
                foreach ($src_files as $file) {
                    if (pathinfo($file, PATHINFO_EXTENSION) === 'h') {
                        $filename = basename($file);
                        $dest = $include_dir . '/' . $filename;
                        if (!copy($file, $dest)) {
                            throw new FileSystemException("Failed to copy file: $file to $dest");
                        }
                    }
                }
            }
        }

        $has_configure = file_exists($source . '/configure');
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
                ->execWithEnv('cmake -DCMAKE_BUILD_TYPE=Release -DCMAKE_INSTALL_PREFIX= -DBUILD_SHARED_LIBS=OFF -DCMAKE_C_FLAGS="-fPIC" -DCMAKE_CXX_FLAGS="-fPIC" .')
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
                ->execWithEnv("make -j {$this->builder->concurrency}")
                ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);

        } else {
            throw new RuntimeException('Nenhum sistema de build detectado (sem configure/Makefile.in ou CMakeLists.txt)');
        }

        $this->patchPkgconfPrefix(['opus.pc'], PKGCONF_PATCH_PREFIX);
    }
}
