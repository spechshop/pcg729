<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$data = [
    'path' => '我是一端比较牛逼的终端打印工具',
    'php_version' => PHP_VERSION,
    'php_version_id' => PHP_VERSION_ID,
    'info' => '并且支持中文！！',
    'path2' => __DIR__,
    '中文级别的key' => '我可以' . str_repeat('很长', 50),
];

(new \ZM\Logger\TablePrinter($data))
    ->setValueColor('random')
    ->setRowOverflowHide()
    ->printAll();
