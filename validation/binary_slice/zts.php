<?php
function check($v) { if (!$v) throw new RuntimeException('slice integrity'); }
if (Swoole\Thread::getArguments() !== null) {
    $source = new ByteBuffer(256);
    for ($i=0; $i<2000; ++$i) {
        $data = pack('N', $i)."\0".str_repeat('a',160);
        $source->clear(); $source->append($data);
        check(psampler_binary_slice($source, 4) === substr($data, 4));
    }
    Swoole\Coroutine\run(function () {
        $rx = new Swoole\Coroutine\Socket(AF_INET, SOCK_DGRAM, 0);
        $tx = new Swoole\Coroutine\Socket(AF_INET, SOCK_DGRAM, 0);
        check($rx->bind('127.0.0.1', 0)); $port=$rx->getsockname()['port'];
        $source=new Swoole\Buffer(256); $peer=null;
        for ($i=0;$i<200;++$i) {
            $data=pack('N',$i)."\0payload";
            check($tx->sendto('127.0.0.1',$port,$data)===strlen($data));
            check($rx->recvfromInto($source,$peer,1)===strlen($data));
            $copy=psampler_binary_slice($source,4); $source->clear();
            check($copy==="\0payload");
        }
        $rx->close(); $tx->close();
    });
    echo "thread slices OK\n";
} else {
    $threads=[];
    for ($i=0;$i<4;++$i) $threads[]=new Swoole\Thread(__FILE__, true);
    foreach ($threads as $t) check($t->join() && $t->getExitStatus()===0);
    echo "4 ZTS threads: 8000 ByteBuffer and 800 UDP slices OK\n";
}
