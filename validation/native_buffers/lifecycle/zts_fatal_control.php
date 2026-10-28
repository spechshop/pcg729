<?php
$args = Swoole\Thread::getArguments();
if ($args !== null) {
    [$counter] = $args;
    register_shutdown_function(function() use($counter) { $counter->add(1); });
    require __DIR__.'/frame_control.php';
} else {
    $counter=new Swoole\Thread\Atomic(0);$threads=[];
    for($i=0;$i<4;++$i)$threads[]=new Swoole\Thread(__FILE__,$counter);
    foreach($threads as $thread) {
        if (!$thread->join() || $thread->getExitStatus() !== 255) throw new RuntimeException('fatal propagation');
    }
    if ($counter->get() !== 4) throw new RuntimeException('ZTS control count');
    echo "ZTS 4 fatal PHP controls: status 255 preserved\n";
}
