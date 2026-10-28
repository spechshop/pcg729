--TEST--
Native Buffer pins survive removal of the last PHP owner and release after exceptions
--EXTENSIONS--
swoole
swoole_buffer_test
--FILE--
<?php
require __DIR__ . '/common.inc';
foreach ([false, true] as $throw) {
    $source = new Swoole\Buffer(16);
    $weak = WeakReference::create($source);
    try {
        $result = swoole_buffer_test_read($source, 0, null, function () use (&$source, $weak, $throw) {
            $source = null;
            gc_collect_cycles();
            ensure($weak->get() !== null);
            if ($throw) { throw new RuntimeException('reader'); }
        });
        ensure(!$throw && $result['length'] === 0);
    } catch (RuntimeException $error) {
        ensure($throw && $error->getMessage() === 'reader');
    }
    ensure($source === null && $weak->get() === null);
}
$buffer = new Swoole\Buffer(16);
for ($i = 0; $i < 100; ++$i) { ensure(swoole_buffer_test_protocol($buffer)); }
$weak = WeakReference::create($buffer);
unset($buffer);
ensure($weak->get() === null);
// If the nested guards unbalanced the Fiber block counter, start would fail.
$fiber = new Fiber(fn() => null);
$fiber->start();
echo "lifetime and references OK\n";
?>
--EXPECT--
lifetime and references OK
