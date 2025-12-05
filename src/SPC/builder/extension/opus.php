<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('opus')]
class opus extends Extension
{
    public function getUnixConfigureArg($shared=false): string
    {
        // 🧠 forçar uso de libsoxr e opus estáticos
        $include = BUILD_ROOT_PATH . '/include';
        $lib     = BUILD_ROOT_PATH . '/lib';

        // adiciona define e link explícito à libsoxr.a e libopus.a
        $cflags  = "-I{$include} -DHAVE_LIBSOXR=1";
        $ldflags = "-L{$lib} -lsoxr -lopus";

        return '--enable-opus '
            . "--with-extra-cflags='{$cflags}' "
            . "--with-extra-ldflags='{$ldflags}'";
    }
}
