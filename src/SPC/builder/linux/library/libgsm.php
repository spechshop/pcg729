<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libgsm extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libgsm;

    public const NAME = 'libgsm';
}
