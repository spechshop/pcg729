--TEST--
Borrow callback cannot enter a new Swoole coroutine from the main context
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
register_shutdown_function(function () use (&$buffer) {
    $buffer->clear();
    ensure(swoole_buffer_test_read($buffer)['length'] === 0);
    echo "creation rejected and guard released\n";
});
swoole_buffer_test_read($buffer, 0, null, function () {
    Swoole\Coroutine::create(function () { echo "unexpected child\n"; });
});
?>
--EXPECTF--
%AFatal error: Cannot switch Swoole coroutine during a native buffer read in %s on line %d
%Acreation rejected and guard released
