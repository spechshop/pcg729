<?php
require dirname(__DIR__,2).'/tests/native_swoole_buffer/common.inc';
$p=new PcmBuffer(8000,1); $p->append("\0\xff\x80\0");
ensure($p->canInvoke('validation.inspect'));
ensure($p->invoke('validation.inspect',null,$p)['size']===4);
ensure($p->invoke('validation.inspect')===['size'=>4,'checksum'=>383,'rate'=>8000,'channels'=>1]);
$p->invoke('validation.inspect',function() use ($p) {
    foreach ([fn()=>$p->clear(),fn()=>$p->append("\0\0"),fn()=>$p->reset(16000,1), fn()=>$p->resample(16000), fn()=>$p->invoke('validation.inspect')] as $f) rejects($f);
    ensure($p->toString()==="\0\xff\x80\0");
});
rejects(fn()=>$p->invoke('validation.inspect',function(){throw new RuntimeException('hook');}),RuntimeException::class);
$p->clear(); ensure($p->invoke('validation.inspect')['size']===0);
for($i=0;$i<10000;++$i) {$p->append("\0\xff");ensure($p->invoke('validation.inspect')['checksum']===255);$p->clear();}
$weak=WeakReference::create($p); unset($p); ensure($weak->get()===null);
echo "PCM native v1 registration, invoke, integrity, guards, exceptions and 10000 cycles PASS\n";
