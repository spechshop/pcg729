<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\RuntimeException;

trait bcg729
{
    /**
     * @throws RuntimeException
     */
    protected function build(): void
    {

        print("

        shell()->cd($this->source_dir)
            ->setEnv([
                'CFLAGS' => trim('-I' . BUILD_INCLUDE_PATH . ' ' . $this->getLibExtraCFlags()),
                'LDFLAGS' => trim('-L' . BUILD_LIB_PATH . ' ' . $this->getLibExtraLdFlags()),
                'LIBS' => $this->getLibExtraLibs(),
            ])
            ->execWithEnv('libtoolize --force --copy')
            ->execWithEnv('./autogen.sh || true && auto') // caso não exista, ignora
            ->execWithEnv('./configure --prefix= --enable-static --disable-shared --with-pic')
            ->execWithEnv('make clean')
            ->execWithEnv('make distclean')
            ->execWithEnv('make')
            ->execWithEnv("make -j {$this->builder->concurrency}")
            ->exec('make install DESTDIR=lib' . BUILD_ROOT_PATH);

        $this->patchPkgconfPrefix(['libbcg729.pc'], PKGCONF_PATCH_PREFIX);
        $this->patchLibs(['libbcg729.la'], PKGCONF_PATCH_LIBS);
        $this->patchLibs(['libbcg729.a'], PKGCONF_PATCH_LIBS);
        $this->patchLibs(['libbcg729.so'], PKGCONF_PATCH_LIBS);
        $this->patchLibs(['libbcg729.so.0'], PKGCONF_PATCH_LIBS);
        $this->patchLibs(['libbcg729.so.0.0.0'], PKGCONF_PATCH_LIBS);


    }
}
