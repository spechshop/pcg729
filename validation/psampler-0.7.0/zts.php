<?php
function ensure(bool $ok): void { if (!$ok) throw new RuntimeException('ZTS isolation failure'); }
$args=Swoole\Thread::getArguments();
if ($args!==null) {
    [$counter]=$args;
    for ($i=0;$i<100;$i++) {
        $a=new AcousticStreamAnalyzer;
        $a->on('signal.changed',static function(array $event): void {});
        $a->push(str_repeat("\0",3200));
        ensure($a->state()['processed_ms']===200);
        $a->reset(); ensure($a->state()['processed_ms']===0);
        $p=new PcmBuffer(48000,1);$p->append(str_repeat("\0",1920));$p->resample(8000);$p->flush();
        $legacy=(new PCMAnalyzer)->analyze(str_repeat("\0",320));
        ensure($legacy['signal']==='silence');
        $counter->add(1);
    }
} else {
    $counter=new Swoole\Thread\Atomic(0);$threads=[];
    for ($i=0;$i<4;$i++) $threads[]=new Swoole\Thread(__FILE__,$counter);
    foreach($threads as $t) ensure($t->join());
    ensure($counter->get()===400);
    echo "ZTS 4 threads: 400 independent AcousticStreamAnalyzer/PCMAnalyzer/PcmBuffer lifecycles OK\n";
}
