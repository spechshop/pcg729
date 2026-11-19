<?php
require __DIR__.'/../../../libspech/plugins/Utils/sip/rtpc.php';
use libspech\Rtp\rtpc;
$mode=$argv[1];
function cpu():float {$r=getrusage(); return $r['ru_utime.tv_sec']+$r['ru_utime.tv_usec']/1e6+$r['ru_stime.tv_sec']+$r['ru_stime.tv_usec']/1e6;}
Co\run(function()use($mode){
 $rx=new Swoole\Coroutine\Socket(AF_INET,SOCK_DGRAM,SOL_UDP);
 $tx=new Swoole\Coroutine\Socket(AF_INET,SOCK_DGRAM,SOL_UDP);
 $rx->bind('127.0.0.1',0); $port=$rx->getsockname()['port']; $b=new Swoole\Buffer(65536);
 $bytes=pack('CCnNN',128,8,42,160,123).str_repeat('a',160);
 $run=function($n)use($mode,$rx,$tx,$port,$b,$bytes){
 for($i=0;$i<$n;$i++){
  if($tx->sendto('127.0.0.1',$port,$bytes)!==172) throw new RuntimeException('send');
  $peer=null;
  if(str_starts_with($mode,'buffer')){
   $len=$rx->recvfromInto($b,$peer,0.2);
   if($len!==172) throw new RuntimeException('receive');
   if($mode==='buffer-parse') $p=rtpc::fromNativeBuffer($b,$len);
  }else{
   $s=$rx->recvfrom($peer,0.2); if(strlen($s)!==172) throw new RuntimeException('receive');
   if($mode==='string-parse') $p=new rtpc($s);
  }
 }
 };
 $run(200); $n=1500; $t=cpu(); $w=hrtime(true); $run($n);
 echo json_encode(['mode'=>$mode,'cpu_ns'=>(cpu()-$t)*1e9/$n,'wall_ns'=>(hrtime(true)-$w)/$n,'n'=>$n]),"\n";
 $rx->close();$tx->close();
});
