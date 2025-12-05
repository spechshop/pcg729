<?php


declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('psampler')]
class psampler extends Extension
{
   public function getUnixConfigureArg($shared=false): string
   {
   var_dump($this->builder);
       return '--enable-psampler '
           . '--with-extra-cflags=-I' . BUILD_ROOT_PATH . '/include '
           . '--with-extra-ldflags=-L' . BUILD_ROOT_PATH . '/lib';
   }

}
