<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libbcg729 extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libbcg729;

    public const NAME = 'libbcg729';
}
