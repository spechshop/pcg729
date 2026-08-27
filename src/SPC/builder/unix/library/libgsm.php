<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

trait libgsm
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        $source = $this->getSourceDir();

        $makefile = $source . '/Makefile';
        $header = $source . '/inc/gsm.h';

        if (!file_exists($makefile)) {
            throw new RuntimeException('libgsm Makefile not found: ' . $makefile);
        }

        if (!file_exists($header)) {
            throw new RuntimeException('libgsm header not found: ' . $header);
        }

        /*
         * O Makefile original da libgsm define CC/CCFLAGS por conta própria.
         * Portanto passamos os valores explicitamente na linha de comando
         * para garantir que o toolchain do SPC seja utilizado.
         */
        $ccFlags = trim(
            '-c ' .
            '-O2 ' .
            '-fPIC ' .
            '-DNeedFunctionPrototypes=1 ' .
            '-Wall ' .
            '-Wno-comment ' .
            '-I' . BUILD_INCLUDE_PATH . ' ' .
            $this->getLibExtraCFlags()
        );

        shell()->cd($source)
            ->execWithEnv('make clean || true')
            ->execWithEnv(
                'make -j ' . $this->builder->concurrency .
                ' lib/libgsm.a' .
                ' CC="$CC"' .
                ' AR="$AR"' .
                ' RANLIB="${RANLIB:-ranlib}"' .
                ' CCFLAGS=' . escapeshellarg($ccFlags)
            );

        $builtLib = $source . '/lib/libgsm.a';

        if (!file_exists($builtLib)) {
            throw new RuntimeException(
                'libgsm static library was not generated: ' . $builtLib
            );
        }

        $installLibDir = BUILD_ROOT_PATH . '/lib';
        $installIncludeDir = BUILD_ROOT_PATH . '/include';

        if (!is_dir($installLibDir)) {
            if (!mkdir($installLibDir, 0755, true) && !is_dir($installLibDir)) {
                throw new FileSystemException(
                    'Failed to create directory: ' . $installLibDir
                );
            }
        }

        if (!is_dir($installIncludeDir)) {
            if (!mkdir($installIncludeDir, 0755, true) && !is_dir($installIncludeDir)) {
                throw new FileSystemException(
                    'Failed to create directory: ' . $installIncludeDir
                );
            }
        }

        /*
         * Normalizamos o resultado para o layout esperado pelo SPC:
         *
         * BUILD_ROOT_PATH/lib/libgsm.a
         * BUILD_ROOT_PATH/include/gsm.h
         */
        if (!copy($builtLib, $installLibDir . '/libgsm.a')) {
            throw new FileSystemException(
                'Failed to install libgsm.a'
            );
        }

        if (!copy($header, $installIncludeDir . '/gsm.h')) {
            throw new FileSystemException(
                'Failed to install gsm.h'
            );
        }
    }
}