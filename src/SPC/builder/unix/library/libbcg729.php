<?php


declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\RuntimeException;
use SPC\exception\FileSystemException;
use SPC\store\FileSystem;

trait libbcg729
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
                        '-I' . $this->source_dir . '/src ' .
                        $this->getLibExtraCFlags()
                    ),
                    'LDFLAGS' => trim(
                        '-L' . BUILD_LIB_PATH . ' ' . $this->getLibExtraLdFlags()
                    ),
                    'LIBS' => $this->getLibExtraLibs(),
                ])
                ->execWithEnv('libtoolize --force --copy')
                ->execWithEnv('./autogen.sh || true') // caso não exista, ignora
                ->execWithEnv('./configure --prefix= --enable-static --disable-shared --with-pic')
                ->execWithEnv("make -j {$this->builder->concurrency}")
                ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);

            $this->patchPkgconfPrefix(['libbcg729.pc'], PKGCONF_PATCH_PREFIX);
            return;
        }


        $source = $this->getSourceDir();
        // copiar os arquivo de source para a pasta de build
        $all_files = $this->listPath($source);

        // copiar todo conteudo de source/libbcg729/src para source/libbcg729/include/bcg729
        $src_dir = $source . '/src';
        $include_dir = $source . '/include/bcg729';

        if (!is_dir($include_dir)) {
            if (!mkdir($include_dir, 0755, true)) {
                throw new FileSystemException("Failed to create directory: $include_dir");
            }
        }

        $src_files = $this->listPath($src_dir);
        if ($src_files === null) {
            throw new FileSystemException("Failed to list files in directory: $src_dir");
        }

        foreach ($src_files as $file) {
            $filename = basename($file);
            $dest = $include_dir . '/' . $filename;
            if (!copy($file, $dest)) {
                throw new FileSystemException("Failed to copy file: $file to $dest");
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

            shell()->cd($source)
                ->execWithEnv('cmake -DCMAKE_BUILD_TYPE=Release -DCMAKE_INSTALL_PREFIX= -DBUILD_SHARED_LIBS=OFF -DCMAKE_C_FLAGS="-fPIC" -DCMAKE_CXX_FLAGS="-fPIC" .')
                ->execWithEnv("make -j {$this->builder->concurrency}")
                ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);

        } elseif ($has_configure) {
            echo "🔧 Detected Autotools project\n";

            // Se não tiver Makefile.in, roda autogen.sh (ou autoreconf -fi como fallback)
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

        $this->patchPkgconfPrefix(['libbcg729.pc'], PKGCONF_PATCH_PREFIX);
    }
}
