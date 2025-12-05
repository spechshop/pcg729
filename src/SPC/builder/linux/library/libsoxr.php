<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libsoxr extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libsoxr;

    public const NAME = 'libsoxr';
}
