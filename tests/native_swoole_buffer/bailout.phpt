--TEST--
Native reader releases borrow and references on Zend bailout (isolated process)
--EXTENSIONS--
swoole
swoole_buffer_test
--INI--
display_errors=1
log_errors=0
html_errors=0
--FILE--
<?php
require __DIR__ . '/common.inc';
$buffer = new Swoole\Buffer(16);
$weak = WeakReference::create($buffer);
register_shutdown_function(function () use (&$buffer, $weak) {
    $buffer->clear();
    ensure(swoole_buffer_test_read($buffer)['length'] === 0);
    $buffer = null;
    ensure($weak->get() === null);
    (new Fiber(fn() => null))->start();
    echo "bailout cleanup OK\n";
});
swoole_buffer_test_read($buffer, 0, null, null, 2);
?>
--EXPECTF--
%AFatal error: Buffer reader fixture bailout in %s on line %d
%Abailout cleanup OK
