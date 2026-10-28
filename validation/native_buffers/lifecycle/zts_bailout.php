<?php
$args = Swoole\Thread::getArguments();
if ($args !== null) {
    [$worker, $counter] = $args;
    $fatal = true; $typed = ($worker % 2) !== 0;
    register_shutdown_function(function() use($counter) {
        global $buffer;
        $buffer->clear();
        ensure(swoole_buffer_test_protocol($buffer));
        $counter->add(1);
    });
    require dirname(__DIR__, 3).'/tests/native_swoole_buffer/recvfrom_into_peer.inc';
} else {
    $counter=new Swoole\Thread\Atomic(0);$threads=[];
    for($i=0;$i<4;++$i)$threads[]=new Swoole\Thread(__FILE__,$i,$counter);
    foreach($threads as $thread) {
        if (!$thread->join() || $thread->getExitStatus() !== 255) throw new RuntimeException('fatal propagation');
    }
    if ($counter->get() !== 4) throw new RuntimeException('ZTS cleanup count');
    echo "ZTS 4 fatal requests: guards released, ABI usable, fatal status 255 preserved\n";
}
