<?php


declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('bcg729')]
class bcg729 extends Extension
{
   public function getUnixConfigureArg(): string
   {
   var_dump($this->builder);
       return '--enable-bcg729 '
           . '--with-extra-cflags=-I' . BUILD_ROOT_PATH . '/include '
           . '--with-extra-ldflags=-L' . BUILD_ROOT_PATH . '/lib';
   }

}
