--TEST--
Borrow callback cannot suspend Swoole coroutine; bailout releases native read
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
    (new Fiber(fn() => null))->start();
    echo "suspension rejected and guard released\n";
});
Swoole\Coroutine\run(function () use (&$buffer) {
    swoole_buffer_test_read($buffer, 0, null, function () {
        Swoole\Coroutine::sleep(0.001);
        echo "unexpected resume\n";
    });
});
?>
--EXPECTF--
%AFatal error: Cannot switch Swoole coroutine during a native buffer read in %s on line %d
%Asuspension rejected and guard released
