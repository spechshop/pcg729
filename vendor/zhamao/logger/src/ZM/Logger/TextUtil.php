<?php

declare(strict_types=1);

namespace ZM\Logger;

class TextUtil
{
    public static function separatorToCamel(string $string, string $separator = '_'): string
    {
        $string = $separator . str_replace($separator, ' ', strtolower($string));
        return ltrim(str_replace(' ', '', ucwords($string)), $separator);
    }
}
