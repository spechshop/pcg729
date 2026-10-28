<?php
require dirname(__DIR__, 3) . '/vendor/autoload.php';
try {
    echo SPC\store\SwooleBufferPatcher::apply($argv[1]) ? "APPLIED\n" : "UNCHANGED\n";
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . "\n");
    exit(1);
}
