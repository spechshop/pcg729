<?php
require dirname(__DIR__, 3).'/tests/native_swoole_buffer/common.inc';
$args = Swoole\Thread::getArguments();
if ($args !== null) {
    [$counter] = $args;
    Swoole\Coroutine\run(function() use($counter) {
        [$rx,$tx,$port] = udp_pair();
        $buffer=new Swoole\Buffer(2048);
        $b0=swoole_buffer_test_refcount($buffer);
        $s0=swoole_buffer_test_refcount($rx);
        for ($i=0;$i<2000;++$i) {
            $packet=pack('V',$i)."\0\xff";
            receive_packet($rx,$tx,$port,$buffer,$packet);
            ensure($buffer->toString()===$packet);
            ensure(swoole_buffer_test_protocol($buffer));
            ensure(swoole_buffer_test_refcount($buffer)===$b0);
            ensure(swoole_buffer_test_refcount($rx)===$s0);
        }
        $peer=null;
        ensure($rx->recvfromInto($buffer,$peer,.001)===false);
        $buffer->clear();
        ensure(swoole_buffer_test_refcount($buffer)===$b0);
        $rx->close();$tx->close();
        $bw=WeakReference::create($buffer);$rw=WeakReference::create($rx);
        $buffer=null;$rx=null;
        ensure($bw->get()===null && $rw->get()===null);
        $counter->add(2000);
    });
} else {
    $counter=new Swoole\Thread\Atomic(0);$threads=[];
    for($i=0;$i<4;++$i)$threads[]=new Swoole\Thread(__FILE__,$counter);
    foreach($threads as $thread)ensure($thread->join());
    ensure($counter->get()===8000);
    echo "ZTS 4 requests: 8000 UDP/read ABI cycles, pins and destruction OK\n";
}
