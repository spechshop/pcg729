<?php
declare(strict_types=1);
function verify($ok) { if (!$ok) throw new RuntimeException('ZTS invariant'); }
$args = Swoole\Thread::getArguments();
if ($args !== null) {
    [$worker,$counter] = $args;
    for ($i=0; $i<10000; ++$i) {
        $s = new Swoole\Buffer(2048);
        verify(swoole_buffer_test_protocol($s));
        verify(swoole_buffer_test_read($s)['length']===0);
        $src = new PsamplerBinaryTestBuffer("\0worker".pack('V',$worker));
        $dst = new ByteBuffer(4);
        $dst->appendBuffer($src);
        verify($dst->pop(11)==="\0worker".pack('V',$worker));
        verify($src->stats()===[1,1,false]);
    }
    (new Fiber(fn()=>null))->start();
    $counter->add(10000);
} else {
    $counter=new Swoole\Thread\Atomic(0); $threads=[];
    for ($i=0;$i<4;++$i) $threads[]=new Swoole\Thread(__FILE__,$i,$counter);
    foreach ($threads as $t) verify($t->join());
    verify($counter->get()===40000);
    echo "ZTS 4 threads, 40000 independent object cycles PASS\n";
}
