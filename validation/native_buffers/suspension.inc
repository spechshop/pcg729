<?php
require dirname(__DIR__,2).'/tests/native_swoole_buffer/common.inc';
$b=new Swoole\Buffer(16);$other=new Swoole\Buffer(16);
$fiber=new Fiber(function()use(&$b,&$other){
    swoole_buffer_test_read($b,0,null,function()use(&$other){
        swoole_buffer_test_read($other);
        rejects(fn()=>Fiber::suspend(),FiberError::class);
    });
    Fiber::suspend('released');
});
ensure($fiber->start()==='released');$fiber->resume();ensure($fiber->isTerminated());
Swoole\Coroutine::set(['enable_preemptive_scheduler'=>true]);
Swoole\Coroutine\run(function()use(&$b){
    $ticks=0;$running=true;
    $cid=Swoole\Coroutine::create(function()use(&$running,&$ticks){while($running){++$ticks;Swoole\Coroutine::sleep(.001);}});
    $before=$ticks;
    swoole_buffer_test_read($b,0,null,function()use(&$ticks,$before){$end=hrtime(true)+50000000;while(hrtime(true)<$end){}ensure($ticks===$before);});
    Swoole\Coroutine::sleep(.01);ensure($ticks>$before);$running=false;Swoole\Coroutine::sleep(.002);
});
echo "Fiber suspend, nested guards, automatic preemption deferral and scheduler recovery PASS\n";
