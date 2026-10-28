<?php
require dirname(__DIR__,2).'/tests/native_swoole_buffer/common.inc';
$p=new PcmBuffer(8000,1);$p->append("\0\0");
register_shutdown_function(function()use($p){
    try {$p->clear();echo "PCM bailout cleanup PASS\n";}
    catch(Throwable $e){echo 'PCM GUARD LEAK: ',$e->getMessage(),"\n";}
});
$p->invoke('validation.inspect',function(){trigger_error('isolated PCM handler bailout',E_USER_ERROR);});
